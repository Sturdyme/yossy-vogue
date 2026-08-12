const express = require("express");
const cors = require("cors");
const dotenv = require("dotenv");
const OpenAI = require("openai");
const pool = require("./db"); // Import pooled database connection

dotenv.config();

const app = express();



app.use(
  cors({
    origin: [
      "https://yossy-vogue.vercel.app",
      "http://localhost:3000",
      "http://localhost:5173",
      "http://127.0.0.1:5173",
      "http://127.0.0.1:3000",
    ],
    methods: ["GET", "POST", "OPTIONS"],
    allowedHeaders: ["Content-Type", "Authorization"],
  })
);

app.use(express.json());

const client = new OpenAI({
  apiKey: process.env.OPENAI_API_KEY,
});

const orderIdRegex = /\b(?:YV-|ORD-)?([a-zA-Z0-9]{6,12})\b/i;

app.post("/api/chat", async (req, res) => {
  try {
    const { message} = req.body;
    const { orderId } = req.body;

    if (!orderId && !message) {
      const match = message.match(orderIdRegex);
      if(match) {
        orderId = match[1];
      }
    }

    let orderContext = "No specific order requested or provided.";

    // Query database if an order ID/Reference was provided
    if (orderId) {
      try {
        // Parameterized query ($1) prevents SQL injection attacks
        const queryText = `
          SELECT id, status, total_amount, created_at 
          FROM orders 
          WHERE id::text = $1 OR reference = $1 
          LIMIT 1
        `;
        
        const dbResult = await pool.query(queryText, [orderId]);

        if (dbResult.rows.length > 0) {
          const order = dbResult.rows[0];
          orderContext = `
            Found Order Details:
            - Order Ref/ID: ${order.id}
            - Current Status: ${order.status}
            - Total Amount: ₦${order.total_amount}
            - Date Created: ${order.created_at}
          `;
        } else {
          orderContext = `Order reference "${orderId}" was searched, but no record was found in the database.`;
        }
      } catch (dbError) {
        console.error("Database Lookup Error:", dbError);
        orderContext = "Error looking up order details in the system.";
      }
    }

    // Call OpenAI GPT-4o-mini
    const aiResponse = await client.chat.completions.create({
      model: "gpt-4o-mini",
      messages: [
        {
          role: "system",
          content: `
You are the official customer service assistant for YunaVogue fashion store.

Store Rules:
- Answer questions regarding orders, shipping, payment methods, and store information.
- Currency is Nigerian Naira (₦).
- Keep answers concise, helpful, and courteous.

Context from Database:
${orderContext}
`,
        },
        { role: "user", content: message },
      ],
    });

    res.json({ reply: aiResponse.choices[0].message.content });
  } catch (error) {
    console.error("Server API Error:", error);
    res.status(500).json({
      reply: "I'm having trouble connecting right now. Please try again in a moment.",
    });
  }
});

// Render dynamic port assignment
const PORT = process.env.PORT || 5001;
if (!process.env.VERCEL) {
  app.listen(PORT, () => {
    console.log(`Server listening on port ${PORT}`);
  });
}

module.exports = app;
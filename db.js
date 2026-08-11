// db.js
const { Pool } = require("pg");
require("dotenv").config();

// Throw explicit error if DATABASE_URL is missing
if (!process.env.DATABASE_URL) {
  throw new Error("FATAL: DATABASE_URL environment variable is missing.");
}

const isProduction = process.env.NODE_ENV === "production";

// Configure Connection Pool
const pool = new Pool({
  connectionString: process.env.DATABASE_URL,
  // Enable SSL in production or when connecting to a remote host (Render)
  ssl: isProduction || process.env.DATABASE_URL.includes("render.com")
    ? { rejectUnauthorized: false }
    : false,
  // Connection Pool limits for free/hobby tiers
  max: 10,                 // Maximum 10 open clients in pool
  idleTimeoutMillis: 30000, // Close idle clients after 30s
  connectionTimeoutMillis: 2000, // Return error after 2s if connection fails
});

// Test connection on server init
pool.connect((err, client, release) => {
  if (err) {
    console.error("❌ PostgreSQL Connection Failed:", err.message);
  } else {
    console.log("✅ Successfully connected to Render PostgreSQL Database");
    release();
  }
});

module.exports = pool;
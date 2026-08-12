// db.js
require("dotenv").config();

const isProduction = process.env.NODE_ENV === "production";
const usePostgres = !!process.env.DATABASE_URL;

let pool;

if (usePostgres) {
  const { Pool } = require("pg");

  pool = new Pool({
    connectionString: process.env.DATABASE_URL,
    ssl:
      isProduction || process.env.DATABASE_URL.includes("render.com")
        ? { rejectUnauthorized: false }
        : false,
    max: 10,
    idleTimeoutMillis: 30000,
    connectionTimeoutMillis: 2000,
  });

  pool.connect((err, client, release) => {
    if (err) {
      console.error("❌ PostgreSQL Connection Failed:", err.message);
    } else {
      console.log("✅ Successfully connected to PostgreSQL");
      release();
    }
  });
} else if (process.env.DB_CONNECTION === "mysql" || process.env.DB_HOST) {
  const mysql = require("mysql2/promise");

  const mysqlPool = mysql.createPool({
    host: process.env.DB_HOST || "127.0.0.1",
    port: process.env.DB_PORT ? Number(process.env.DB_PORT) : 3306,
    user: process.env.DB_USERNAME,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_DATABASE,
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0,
  });

  pool = {
    query: async (sql, params = []) => {
      const [rows] = await mysqlPool.query(sql, params);
      return { rows };
    },
    end: async () => mysqlPool.end(),
  };

  mysqlPool.getConnection()
    .then((conn) => {
      console.log("✅ Successfully connected to MySQL database");
      conn.release();
    })
    .catch((err) => {
      console.error("❌ MySQL Connection Failed:", err.message);
    });
} else {
  throw new Error(
    "FATAL: DATABASE_URL or MySQL environment variables are required to connect to the database."
  );
}

module.exports = pool;
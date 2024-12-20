-- First connect to postgres database to create new database
\c postgres;

-- Drop database if exists (uncomment if needed)
-- DROP DATABASE IF EXISTS events_db;

CREATE DATABASE events_db;

-- Now connect to the new database
\c events_db;

CREATE TABLE IF NOT EXISTS events (
    id SERIAL PRIMARY KEY,
    event_name VARCHAR(255) NOT NULL,
    time_required VARCHAR(50) NOT NULL,
    share_link VARCHAR(255) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
); 
#!/bin/bash

# Database configuration
DB_NAME="events_db"
DB_USER="postgres"

echo "Starting database setup..."

# Check if database exists
if psql -U $DB_USER -lqt | cut -d \| -f 1 | grep -qw $DB_NAME; then
    echo "Database $DB_NAME already exists. Dropping it..."
    psql -U $DB_USER -c "DROP DATABASE $DB_NAME;"
fi

# Create database
echo "Creating database $DB_NAME..."
psql -U $DB_USER -c "CREATE DATABASE $DB_NAME;"

# Create tables
echo "Creating tables..."
psql -U $DB_USER -d $DB_NAME -c "
CREATE TABLE IF NOT EXISTS events (
    id SERIAL PRIMARY KEY,
    event_name VARCHAR(255) NOT NULL,
    time_required VARCHAR(50) NOT NULL,
    share_link VARCHAR(255) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS date_preferences (
    id SERIAL PRIMARY KEY,
    event_id INTEGER REFERENCES events(id) ON DELETE CASCADE,
    start_date TIMESTAMP NOT NULL,
    end_date TIMESTAMP NOT NULL,
    preference_score INTEGER CHECK (preference_score BETWEEN 1 AND 10),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);"

echo "Database setup completed successfully!" 
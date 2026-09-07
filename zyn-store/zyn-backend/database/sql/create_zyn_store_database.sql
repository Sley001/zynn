-- Run this once in pgAdmin 4 while connected to the default "postgres" database.
-- If zyn_store already exists, do not run it again.

CREATE DATABASE zyn_store
    WITH
    OWNER = postgres
    ENCODING = 'UTF8'
    TEMPLATE = template0
    CONNECTION LIMIT = -1;

COMMENT ON DATABASE zyn_store IS 'PostgreSQL database for ZYN Reserve Cambodia';

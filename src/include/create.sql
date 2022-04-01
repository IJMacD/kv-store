PRAGMA foreign_keys=OFF;
BEGIN TRANSACTION;
CREATE TABLE Buckets (name text primary key, created_date text default current_timestamp, enabled int default 1);
CREATE TABLE Auth (id integer primary key, bucket_name text, auth_type text, identifier text, secret text, expires_date text, can_list int default 0, can_read int default 0, can_create int default 0, can_edit int default 0, can_delete int default 0);
CREATE TABLE Objects (bucket_name text, key text, value text, created_date text default current_timestamp);
CREATE UNIQUE INDEX ObjectKeys ON Objects (bucket_name, key);
CREATE INDEX ObjectCreated ON Objects (bucket_name, created_date);
COMMIT;

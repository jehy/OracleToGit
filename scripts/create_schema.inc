-- Create the user
create user MAGIC identified by "YOUR_PASSWORD"
default tablespace APP_TABLESPACE --your tablespace name
temporary tablespace TEMP_TABLESPACE --your temporary tablespace name
profile DEFAULT;
-- Grant/Revoke role privileges
grant connect to MAGIC;
grant resource to MAGIC;
grant dba to MAGIC;
-- Grant/Revoke system privileges
grant create session to MAGIC;
grant unlimited tablespace to MAGIC;
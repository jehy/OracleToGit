-- Create the user 
create user MAGIC identified by "YOUR_PASSWORD"
  default tablespace EIAS
  temporary tablespace TEMP01
  profile DEFAULT;
-- Grant/Revoke object privileges 
grant select on DBA_MVIEWS to MAGIC;
grant execute on DBMS_LOCK to MAGIC;
-- Grant/Revoke role privileges 
grant connect to MAGIC;
grant resource to MAGIC;
-- Grant/Revoke system privileges 
grant create session to MAGIC;
grant create synonym to MAGIC;
grant create view to MAGIC;
grant unlimited tablespace to MAGIC;
grant dba to MAGIC;
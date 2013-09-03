-- Create table
create table MAGIC.DDLLOG
(
  ddl_timestamp   DATE,
  sysevent        VARCHAR2(100),
  login_user      VARCHAR2(50),
  instance_num    NUMBER,
  database_name   VARCHAR2(50),
  dict_obj_name   VARCHAR2(100),
  dict_obj_type   VARCHAR2(100),
  dict_obj_owner  VARCHAR2(50),
  host            VARCHAR2(100),
  ip              VARCHAR2(15),
  os_user         VARCHAR2(50),
  obj_current_ddl CLOB,
  event_id        NUMBER
)
tablespace EIAS
  pctfree 10
  initrans 1
  maxtrans 255
  storage
  (
    initial 64K
    next 1M
    minextents 1
    maxextents unlimited
  );
-- Create/Recreate indexes 
create index MAGIC.DDLLOG_ID on MAGIC.DDLLOG (EVENT_ID)
  tablespace EIAS_MOD
  pctfree 10
  initrans 2
  maxtrans 255
  storage
  (
    initial 64K
    next 1M
    minextents 1
    maxextents unlimited
  );


-- Create sequence 
create sequence MAGIC.EVENT_IDS
minvalue 1
maxvalue 1000000000000
start with 1
increment by 1
cache 20;


create or replace trigger magic.tgr_id
before insert or update on magic.ddllog
for each row
declare
begin
  if (inserting and :new.event_id is null or :new.event_id <= 0) then
    select magic.event_IDS.nextval into :new.event_id from dual ;
  end if ;
end ;

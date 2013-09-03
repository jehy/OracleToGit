create or replace type magic.after_ddl_queue_type as object
    (
     ddl_timestamp date,
     sysevent varchar2(100),
     ora_login_user varchar2(50),
     ora_instance_num number,
     ora_database_name varchar2(50),
     ora_dict_obj_name varchar2(100),
     ora_dict_obj_type varchar2(100),
     ora_dict_obj_owner varchar2(50),
     host varchar2(100),
     ip varchar2(15),
     os_user varchar2(50),
     ddl_text clob
    );

create or replace trigger magic.after_ddl after ddl on database
declare
 ddl_text_var dbms_standard.ora_name_list_t;       --This is the type ora_name_list_t, is table of varchar2(64)
 full_ddl_text clob;                               --There will be stored the full DDL text
 --enqueue_options dbms_aq.enqueue_options_t;        --Enqueue Options Type
 --message_properties dbms_aq.message_properties_t;  --Message Properties Type
 message after_ddl_queue_type;
 --msgid raw(16);                                    --Message ID, it will be returned from the dbms_aq.enqueue procedure
begin
  if(ora_sysevent in ('TRUNCATE','ANALYZE'))
  then
    null;--smth may be here in future
  else
 for i in 1..ora_sql_txt(ddl_text_var) loop        --This portion of code calculates the full DDL text, because ddl_text_var
   full_ddl_text:=full_ddl_text||ddl_text_var(i);  --is just a table of 64 byte pieces of DDL, we need to subtract them
 end loop;                                 --to get full DDL.
 message:=after_ddl_queue_type(sysdate,
                               ora_sysevent,
                               ora_login_user,
                               ora_instance_num,
                               ora_database_name,
                               ora_dict_obj_name,
                               ora_dict_obj_type,
                               ora_dict_obj_owner,
                               sys_context('userenv','HOST'),
                               sys_context('userenv','IP_ADDRESS'),
                               sys_context('userenv','OS_USER'),
                               full_ddl_text);
 insert into magic.ddllog values(message.ddl_timestamp,message.sysevent,message.ora_login_user,message.ora_instance_num
 ,message.ora_database_name,message.ora_dict_obj_name,message.ora_dict_obj_type,message.ora_dict_obj_owner,message.host,
 message.ip,message.os_user,message.ddl_text,null);
 /*if ora_sysevent='DROP' then
   message_properties.delay:=1;
 end if;
 dbms_aq.enqueue(queue_name=>'SYS.AFTER_DDL_QUEUE',
                 enqueue_options=>enqueue_options,
                 message_properties=>message_properties,
                 payload=> message,
                 msgid=>msgid);*/
                 end if;
end;


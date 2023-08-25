alter table vhost add column product_id INTEGER NOT NULL DEFAULT 0;
alter table vhost add column flow INTEGER NOT NULL DEFAULT 0;
alter table vhost add column hcount INTEGER NOT NULL DEFAULT 0;
alter table vhost add column cs INTEGER NOT NULL DEFAULT 0;
alter table vhost add column envs TEXT DEFAULT NULL;
alter table vhost add column cdn INTEGER NOT NULL DEFAULT 0;
ALTER TABLE vhost ADD COLUMN [db_type] VARCHAR(16)  NULL;
ALTER TABLE vhost ADD COLUMN [ext_passwd] INTEGER DEFAULT 0;
CREATE TABLE [manynode] (
	[name] VARCHAR(255)  NOT NULL PRIMARY KEY,
	[host] VARCHAR(255)  NULL,
	[mem] VARCHAR(255)  NULL
);
ALTER TABLE vhost ADD COLUMN [log_handle] INTEGER DEFAULT 0;
ALTER TABLE product ADD COLUMN [log_handle] INTEGER DEFAULT 0;

alter table product add column cs INTEGER NOT NULL DEFAULT 0;
alter table product add column envs TEXT DEFAULT NULL;
alter table product add column cdn INTEGER NOT NULL DEFAULT 0;
alter table product add column	max_worker INTEGER DEFAULT 0 NULL;
alter table product add column	max_queue INTEGER DEFAULT 0 NULL;
alter table product add column max_subdir INTEGER NOT NULL DEFAULT 0;
alter table vhost add column max_subdir INTEGER NOT NULL DEFAULT 0;
ALTER TABLE product ADD COLUMN [db_type] VARCHAR(16)  NULL;
alter table manynode add column syncstatus INTEGER  NULL;
alter table manynode add column synctime datatime  NULL;
alter table vhost add column expire_time2 INTEGER NOT NULL DEFAULT 0;
alter table manynode add column skey TEXT NULL;
alter table vhost add column sync_seq INTEGER NOT NULL DEFAULT 0;
alter table vhost add column flow_limit INTEGER NOT NULL DEFAULT 0;
alter table product add column flow_limit INTEGER NOT NULL DEFAULT 0;

alter table vhost_info add column id INTEGER NOT NULL DEFAULT 1000;
CREATE INDEX [IDX_VHOST_INFO_ID] ON [vhost_info]([id]  ASC);
alter table vhost add column ftp_connect INTEGER NOT NULL DEFAULT 0;
alter table vhost add column ftp_usl INTEGER NOT NULL DEFAULT 0;
alter table vhost add column ftp_dsl INTEGER NOT NULL DEFAULT 0;
alter table product add column ftp_connect INTEGER NOT NULL DEFAULT 0;
alter table product add column ftp_usl INTEGER NOT NULL DEFAULT 0;
alter table product add column ftp_dsl INTEGER NOT NULL DEFAULT 0;
ALTER TABLE product ADD COLUMN [module] TEXT  NULL;
ALTER TABLE vhost ADD COLUMN [module] TEXT  NULL;
ALTER TABLE vhost ADD COLUMN [certificate] TEXT  NULL;
ALTER TABLE vhost ADD COLUMN [certificate_key] TEXT  NULL;
ALTER TABLE product ADD COLUMN [ip] TEXT  NULL;
ALTER TABLE vhost ADD COLUMN [ip] TEXT  NULL;
ALTER TABLE product ADD COLUMN [port] TEXT  NULL;
ALTER TABLE vhost ADD COLUMN [port] TEXT  NULL;
ALTER TABLE product ADD COLUMN [ssi] INTEGER;
ALTER TABLE vhost ADD COLUMN [ssi] INTEGER;
ALTER TABLE vhost ADD COLUMN [ftp_subdir] TEXT  NULL;
alter table vhost add column last_password_error TIMESTAMP NOT NULL DEFAULT 0;
alter table vhost add column password_error_count INTEGER NOT NULL DEFAULT 0;
alter table vhost add column password_security TEXT NULL;
alter table vhost add column ignore_backup INTEGER NOT NULL DEFAULT 0;
alter table product add column ignore_backup INTEGER NOT NULL DEFAULT 0;
alter table vhost add column cron INTEGER NOT NULL DEFAULT 0;
alter table product add column cron INTEGER NOT NULL DEFAULT 0;
alter table product add column [default_index] TEXT  NULL;
CREATE TABLE [cron] (
	[id] INTEGER  NOT NULL PRIMARY KEY AUTOINCREMENT,
	[vhost] TEXT  NULL,
	[type] TEXT NULL,
	[min] TEXT  NULL,
	[hour] TEXT  NULL,
	[day] TEXT  NULL,
	[month] TEXT  NULL,
	[mday] TEXT  NULL,
	[cmd_type] INTEGER DEFAULT 0 NOT NULL,
	[cmd] TEXT  NULL,
	[stdin_file] TEXT  NULL,
	[stdout_file] TEXT  NULL,
	[stderr_file] INTEGER DEFAULT 1 NOT NULL
);
CREATE INDEX [IDX_CRON_VHOST] ON [cron] (
	[vhost]  ASC
);
CREATE TABLE [httpauth] (
	[vhost] TEXT  NULL,
	[user] TEXT  NULL,
	[passwd] TEXT  NULL,
	PRIMARY KEY ([vhost],[user])
);
CREATE INDEX [IDX_VHOST_INFO_VHOST] ON [vhost_info](
	[vhost]  ASC
);
alter table vhost add column recordid INTEGER NOT NULL DEFAULT 0;
alter table vhost add column http2 INTEGER NOT NULL DEFAULT 0;
alter table manynode add column port INTEGER NOT NULL DEFAULT 3312;

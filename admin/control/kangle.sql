CREATE TABLE `setting` (
	[name] text  NULL PRIMARY KEY,
	[value] text  NULL
);
CREATE TABLE `vhost` (
	[name] TEXT  UNIQUE NULL,
	[passwd] TEXT  NULL,
	[doc_root] TEXT  NULL,
	[uid] TEXT  NOT NULL PRIMARY KEY,
	[gid] TEXT NOT NULL DEFAULT 1100,
	[module] TEXT  NULL,
	[templete] TEXT  NULL,
	[subtemplete] TEXT  NULL,
	[create_time] INTEGER  NULL,
	[expire_time2] INTEGER  NOT NULL DEFAULT 0,
	[status] INTEGER NOT NULL DEFAULT 0,
	[web_quota] INTEGER  NULL,
	[db_quota] INTEGER  NULL,
	[subdir_flag] INTEGER  NULL,
	[subdir] TEXT  NULL,
	[domain] INTEGER  NULL,
	[htaccess] TEXT  NULL,
	[access] TEXT NULL,
	[ip] TEXT  NULL,
	[port] TEXT NULL,
	[ssi] INTEGER,
	[max_connect] INTEGER NOT NULL DEFAULT 0,
	[speed_limit] INTEGER NOT NULL DEFAULT 0,
	[max_worker] INTEGER NOT NULL DEFAULT 0,
	[max_queue] INTEGER NOT NULL DEFAULT 0,
	[ftp] INTEGER NOT NULL DEFAULT 1,
	[log_file] TEXT NULL,
	[db_name] TEXT NULL,
	[product_id] INTEGER NOT NULL DEFAULT 0,
	[cs] INTEGER NOT NULL DEFAULT 0,
	[flow] INTEGER NOT NULL DEFAULT 0,
	[hcount] INTEGER NOT NULL DEFAULT 0,
	[cdn] INTEGER NOT NULL DEFAULT 0,
	[db_type] text  NULL,
	[ext_passwd] INTEGER NOT NULL DEFAULT 0,
	[envs] TEXT NULL,
	[log_handle] INTEGER NOT NULL DEFAULT 0,
	[max_subdir] INTEGER NOT NULL DEFAULT 0,
	[sync_seq] INTEGER NOT NULL DEFAULT 0,
	[flow_limit] INTEGER NOT NULL DEFAULT 0,
	[certificate] TEXT  NULL,
	[certificate_key] TEXT  NULL,
	[ftp_subdir] TEXT  NULL,
	[ftp_connect] INTEGER NOT NULL DEFAULT 0,
	[ftp_usl] INTEGER NOT NULL DEFAULT 0,
	[ftp_dsl] INTEGER NOT NULL DEFAULT 0,
	[last_password_error] TIMESTAMP NULL,
	[password_error_count] INTEGER NOT NULL DEFAULT 0,
	[password_security] text NULL,
	[ignore_backup] INTEGER NOT NULL DEFAULT 0,
	[cron] INTEGER NOT NULL DEFAULT 0,
	[recordid] INTEGER NOT NULL DEFAULT 0,
	[http2] INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE `vhost_info` (
	[vhost] text NOT NULL,
	[type] tinyint(4) NOT NULL DEFAULT 0,
	[name] text NOT NULL,
	[value] text DEFAULT NULL,
	[id] INTEGER NOT NULL DEFAULT 1000
);
CREATE TABLE `filter` (
	[id] INTEGER PRIMARY KEY,
	[value] TEXT
);
CREATE TABLE `vhost_webapp` (
	[id] INTEGER  NOT NULL PRIMARY KEY AUTOINCREMENT,
	[user] text  NOT NULL,
	[status] integer DEFAULT 0 NOT NULL,
	[install_time] integer DEFAULT NULL,
	[appid] text  NOT NULL,
	[domain] text  NOT NULL,
	[dir] text  NOT NULL,
	[phy_dir] text  NOT NULL,
	[appname] text DEFAULT  NULL,
	[appver] text DEFAULT  NULL
);
CREATE TABLE [product] (
	id integer PRIMARY KEY,
	product_name TEXT UNIQUE,
	module TEXT  NULL,
	templete TEXT,
	subtemplete TEXT,
	web_quota INTEGER,
	db_quota INTEGER,
	subdir_flag INTEGER,
	subdir TEXT,
	domain INTEGER,
	htaccess TEXT,
	access TEXT,
	max_connect INTEGER NOT NULL DEFAULT 0,
	speed_limit INTEGER NOT NULL DEFAULT 0,
	ftp INTEGER NOT NULL DEFAULT 1,
	ssi INTEGER,
	log_file TEXT,
	ip TEXT  NULL,
	port TEXT NULL,
	log_handle INTEGER NOT NULL DEFAULT 0,
	cs INTEGER NOT NULL DEFAULT 0, 
	envs TEXT DEFAULT NULL, 
	cdn INTEGER NOT NULL DEFAULT 0, 
	max_worker INTEGER NOT NULL DEFAULT 0, 
	max_queue INTEGER NOT NULL DEFAULT 0,
	db_type text  NULL,
	max_subdir INTEGER NOT NULL DEFAULT 0,
	flow_limit INTEGER NOT NULL DEFAULT 0,
	ftp_connect INTEGER NOT NULL DEFAULT 0,
	ftp_usl INTEGER NOT NULL DEFAULT 0,
	ftp_dsl INTEGER NOT NULL DEFAULT 0,
	[ignore_backup] INTEGER NOT NULL DEFAULT 0,
	[cron] INTEGER NOT NULL DEFAULT 0,
	[default_index] TEXT  NULL
  );
  
CREATE INDEX [vhost_info_name] ON [vhost_info](
	[user]  DESC,
	[name]  DESC,
	[type]  DESC
);

CREATE INDEX [vhost_webapp_user] ON [vhost_webapp](
	[user]  DESC
);
CREATE TABLE [manynode] (
	[name] text  NOT NULL PRIMARY KEY,
	[host] text  NULL,
	[skey] text  NULL,
	[mem] text  NULL,
	[syncstatus] INTEGER NULL,
	[synctime] datatime NULL
);
CREATE INDEX [IDX_VHOST_INFO_ID] ON [vhost_info]([id]  ASC);

CREATE TABLE [cron] (
	[id] INTEGER  NOT NULL PRIMARY KEY AUTOINCREMENT,
	[vhost] TEXT  NULL,
	[min] TEXT  NULL,
	[hour] TEXT  NULL,
	[day] TEXT  NULL,
	[month] TEXT  NULL,
	[mday] TEXT  NULL,
	[cmd_type] INTEGER DEFAULT 0  NOT NULL,
	[cmd] TEXT  NULL,
	[stdin_file] TEXT  NULL,
	[stdout_file] TEXT  NULL,
	[stderr_as_out] INTEGER DEFAULT 1 NOT NULL
);
CREATE INDEX [IDX_CRON_VHOST] ON [cron](
	[vhost]  ASC
);
CREATE TABLE [httpauth] (
	[vhost] text  NULL,
	[user] text  NULL,
	[passwd] text  NULL,
	PRIMARY KEY ([vhost],[user])
);
CREATE INDEX [IDX_VHOST_INFO_VHOST] ON [vhost_info](
	[vhost]  ASC
);


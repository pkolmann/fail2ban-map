# Create the needed Database

```sql
create database fail2ban;
create user 'fail2ban'@'localhost' IDENTIFIED BY 'XXX';
create user 'f2b_read'@'localhost' IDENTIFIED BY 'XXX';
GRANT SELECT, INSERT, UPDATE ON fail2ban.* TO 'fail2ban'@'localhost';
GRANT SELECT ON fail2ban.* TO 'f2b_read'@'localhost';
flush privileges;

use fail2ban;
create table user
(
    user_id int auto_increment
        primary key,
    user    varchar(255)         not null,
    hash    varchar(255)         not null,
    upload  tinyint(1) default 0 not null,
    view    tinyint(1) default 0 not null
);

create table log
(
    log_id     int auto_increment
        primary key,
    user_id     int                                  not null,
    time       datetime default current_timestamp() not null,
    service    varchar(255)                         null,
    agent      varchar(255)                         null,
    ip         varchar(255)                         null,
    hostname   varchar(255)                         null,
    countryISO varchar(10)                          null,
    country    varchar(255)                         null,
    subdiv     varchar(255)                         null,
    city       varchar(255)                         null,
    postal     varchar(255)                         null,
    lat        double                               null,
    `long`     double                               null,
    network    varchar(255)                         null,
    constraint log_user_user_id_fk
        foreign key (user_id) references user (user_id)
);

```

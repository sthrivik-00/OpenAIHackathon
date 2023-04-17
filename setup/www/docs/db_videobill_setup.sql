-- Database creation 
DROP DATABASE IF EXISTS db_vsp_explorer;
CREATE DATABASE db_vsp_explorer;
\c db_vsp_explorer;

-- User creation 

FLUSH PRIVILEGES;
CREATE USER 'vspexp'@'localhost';
GRANT ALL PRIVILEGES ON  db_vsp_explorer.* TO 'vspexp'@'localhost' IDENTIFIED BY 'vsp1exp';


-- Table creation

DROP TABLE IF EXISTS bill_plans;

CREATE TABLE bill_plans ( bill_plan_id smallint, 
plan_name varchar(40),
rate decimal(15,4),
description varchar(40),
free_local smallint,
free_national smallint,
free_international smallint,
national_roaming smallint,
international_roaming smallint,
discount_id smallint);


DROP TABLE IF EXISTS charges;

CREATE TABLE charges (charge_id smallint,
charge_rate decimal(15,4),
description varchar(40));


DROP TABLE IF EXISTS discounts;

CREATE TABLE discounts (discount_id smallint,
level tinyint,
flat_rate decimal(15,4),
percentage smallint);


DROP TABLE IF EXISTS usages;

CREATE TABLE usages (type_id_usg smallint,
description varchar(40),
rate decimal(15,4));


DROP TABLE IF EXISTS accounts;

CREATE TABLE accounts (account_no int(10),
external_id varchar(10),
first_name varchar(30),
last_name varchar(30),
address varchar(40),
city varchar(30),
state varchar(30),
country varchar(30),
payment_method varchar(30),
bank_account varchar(30),
bank_ref_no varchar(30), 
expiry_date date);


DROP TABLE IF EXISTS services;

CREATE TABLE services (subscr_no int(10),
external_id varchar(15),
service_type smallint,
bill_plan_id smallint,
account_no int(10));


DROP TABLE IF EXISTS invoice;

CREATE TABLE invoice (account_no int(10),
bill_no int(10),
statement_date date,
from_date date,
to_date date,
total decimal(16,4),
tax decimal(16,4),
total_invoice decimal(16,4),
payment_date date);


DROP TABLE IF EXISTS invoice_details;

CREATE TABLE invoice_details (bill_no int(10),
subscr_no int(10),
type_code smallint,
subtype_code smallint,
amount decimal(15,4),
duration int(10));

-- Data population

Insert into bill_plans values (100,'BTELP',3,'Basic Telephone Package',50,50,30,20,20,NULL);
Insert into bill_plans values (101,'PRES',10,'Plan Base Residencial',60,60,40,30,30,NULL);
Insert into bill_plans values (102,'MPIA2',15,'Plan Integrated Voice +Data 100 MB',100,100,80,50,20,NULL);
Insert into bill_plans values (103,'PEMP',20,'Plan Base Empresas',150,150,90,60,30,NULL);
Insert into bill_plans values (104,'SSTAC',30,'Plan Smart S',200,200,100,80,30,NULL);
Insert into bill_plans values (105,'TVOP',20,'Plan TV',NULL,NULL,NULL,NULL,NULL,302);


Insert into charges values (200,20,'Leased Line Installation Charge');
Insert into charges values (201,10,'Installation of Telephone');
Insert into charges values (202,30,'Termination of Telephone');
Insert into charges values (203,20,'Finance charge');
Insert into charges values (204,10,'Try&buy Find&GO');
Insert into charges values (205,30,'TV installation charge');


Insert into discounts values (300,2,10,NULL);
Insert into discounts values (301,2,20,NULL);
Insert into discounts values (302,2,NULL,5);
Insert into discounts values (303,1,15,NULL);
Insert into discounts values (304,1,25,NULL);
Insert into discounts values (305,1,NULL,10);


Insert into usages values (400,'Local',0.15);
Insert into usages values (401,'National',0.6);
Insert into usages values (402,'International',1.5);
Insert into usages values (403,'National roaming',0.4);
Insert into usages values (404,'International roaming',8);



Insert into accounts values (1,'22561375','Jose','Barraquer','111, Sec 4, Vihar','New Delhi','New Delhi','India','Cash','','',NULL);
Insert into accounts values (2,'19162029','Ángel','Cabrera','1234, Bangalore','Bangalore','Karnataka','India','Online','11098786','',NULL);
Insert into accounts values (3,'11636205','Julio','Baviera','442, Street 2','Bangalore','Karnataka','India','CreditCard','989878791730','',43647);
Insert into accounts values (4,'9501220','Avelino','Canós','56, Sec 4, Gurgaon','New Delhi','New Delhi','India','Cash','','',NULL);
Insert into accounts values (5,'21448363','Fausto','Elhúyar ','789 Gaziabad','New Delhi','New Delhi','India','Online','11098896','',NULL);
Insert into accounts values (6,'15216227','Antoni','Gimbernat','123 Nehruplace','New Delhi','New Delhi','India','CreditCard','989878791778','',43586);
Insert into accounts values (7,'16782477','Abdul','Kalam','456 Okla','New Delhi','New Delhi','India','Cash','','',NULL);
Insert into accounts values (8,'24528672','Emilio','Linares ','092- 2nd floor South Ex','New Delhi','New Delhi','India','Online','11098791','',NULL);
Insert into accounts values (9,'23688540','Severo','Ochoa','78- 1st Floor Lajpat','New Delhi','New Delhi','India','CreditCard','98987878761','',43709);
Insert into accounts values (10,'21480443','Isaac','Peral','890- Cyber Hub','Noida','UP','India','Online','156098786','',NULL);


Insert into services values (500,'663190506',1,100,1);
Insert into services values (501,'670990298',1,100,2);
Insert into services values (502,'687452620',1,101,3);
Insert into services values (503,'687452608',1,101,4);
Insert into services values (504,'687465118',1,102,5);
Insert into services values (505,'662121799',1,102,6);
Insert into services values (506,'673361650',1,103,7);
Insert into services values (507,'662121799',1,103,8);
Insert into services values (508,'687466760',1,104,9);
Insert into services values (509,'687467425',1,104,10);
Insert into services values (510,'663193677',1,100,1);
Insert into services values (511,'699933559',1,100,2);
Insert into services values (512,'679571512',1,101,3);
Insert into services values (513,'687466760',1,101,5);
Insert into services values (514,'673361650',1,102,5);
Insert into services values (515,'662121799',1,103,6);
Insert into services values (516,'V21614240',2,105,6);
Insert into services values (517,'680880298',1,103,7);
Insert into services values (518,'677452720',1,104,8);
Insert into services values (519,'687487298',1,104,9);
Insert into services values (520,'V29014240',2,105,10);
Insert into services values (521,'687462385',1,100,1);
Insert into services values (522,'663198197',1,100,2);


Insert into invoice values (1,111,'20200102','20191201','20191231',330,69.3,399.3,'20200120');
Insert into invoice values (1,112,'20200202','20200101','20200131',400,84,484,'20200220');
Insert into invoice values (1,113,'20200302','20200201','20200229',300,63,363,'2020030');
Insert into invoice values (1,114,'20200402','20200301','20200331',500,105,605,'20200420');
Insert into invoice values (2,211,'20200102','20191201','20191231',400,84,484,'20200120');
Insert into invoice values (3,311,'20200102','20191201','20191231',200,42,242,'20200120');
Insert into invoice values (4,411,'20200102','20191201','20191231',500,105,605,'20200120');
Insert into invoice values (5,511,'20200102','20191201','20191231',1000,210,1210,'20200120');
Insert into invoice values (6,611,'20200102','20191201','20191231',330,69.3,399.3,'20200120');
Insert into invoice values (7,711,'20200102','20191201','20191231',189,39.69,228.69,'20200120');
Insert into invoice values (8,811,'20200102','20191201','20191231',907,190.47,1097.47,'20200120');
Insert into invoice values (9,911,'20200102','20191201','20191231',876,183.96,1059.96,'20200120');
Insert into invoice values (10,1011,'20200102','20191201','20191231',190,39.9,229.9,'20200120');



Insert into invoice_details values (111,500,2,100,3,NULL);
Insert into invoice_details values (111,500,3,201,10,NULL);
Insert into invoice_details values (111,500,5,300,10,NULL);
Insert into invoice_details values (111,500,7,400,15,100);
Insert into invoice_details values (111,500,7,401,18,30);
Insert into invoice_details values (111,500,7,402,15,10);
Insert into invoice_details values (111,501,2,100,3,NULL);
Insert into invoice_details values (111,501,3,201,10,NULL);
Insert into invoice_details values (111,501,7,400,3,20);




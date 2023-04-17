--
-- PostgreSQL database cluster dump
--

SET default_transaction_read_only = off;

SET client_encoding = 'SQL_ASCII';
SET standard_conforming_strings = on;

--
-- Roles
--

CREATE ROLE postgres;
ALTER ROLE postgres WITH SUPERUSER INHERIT CREATEROLE CREATEDB LOGIN REPLICATION PASSWORD 'md54a35d879f25ace3e469f1c9821973d1a';
CREATE ROLE vspexp;
ALTER ROLE vspexp WITH NOSUPERUSER INHERIT NOCREATEROLE NOCREATEDB LOGIN NOREPLICATION;






--
-- Database creation
--

REVOKE ALL ON DATABASE template1 FROM PUBLIC;
REVOKE ALL ON DATABASE template1 FROM postgres;
GRANT ALL ON DATABASE template1 TO postgres;
GRANT CONNECT ON DATABASE template1 TO PUBLIC;
CREATE DATABASE videobill WITH TEMPLATE = template0 OWNER = postgres;
CREATE DATABASE videobill2 WITH TEMPLATE = template0 OWNER = postgres;


\connect postgres

SET default_transaction_read_only = off;

--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'SQL_ASCII';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET client_min_messages = warning;

--
-- Name: DATABASE postgres; Type: COMMENT; Schema: -; Owner: postgres
--

COMMENT ON DATABASE postgres IS 'default administrative connection database';


--
-- Name: plpgsql; Type: EXTENSION; Schema: -; Owner: 
--

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;


--
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


--
-- Name: SCHEMA public; Type: ACL; Schema: -; Owner: postgres
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM postgres;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO PUBLIC;


--
-- PostgreSQL database dump complete
--

\connect template1

SET default_transaction_read_only = off;

--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'SQL_ASCII';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET client_min_messages = warning;

--
-- Name: DATABASE template1; Type: COMMENT; Schema: -; Owner: postgres
--

COMMENT ON DATABASE template1 IS 'default template for new databases';


--
-- Name: plpgsql; Type: EXTENSION; Schema: -; Owner: 
--

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;


--
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


--
-- Name: SCHEMA public; Type: ACL; Schema: -; Owner: postgres
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM postgres;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO PUBLIC;


--
-- PostgreSQL database dump complete
--

\connect videobill

SET default_transaction_read_only = off;

--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'SQL_ASCII';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET client_min_messages = warning;

--
-- Name: plpgsql; Type: EXTENSION; Schema: -; Owner: 
--

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;


--
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: accounts; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE public.accounts (
    account_no integer,
    external_id character varying(10),
    first_name character varying(30),
    last_name character varying(30),
    address character varying(40),
    city character varying(30),
    state character varying(30),
    country character varying(30),
    payment_method character varying(30),
    bank_account character varying(30),
    bank_ref_no character varying(30),
    expiry_date date
);


ALTER TABLE public.accounts OWNER TO postgres;

--
-- Name: bill_plans; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE public.bill_plans (
    bill_plan_id smallint,
    plan_name character varying(40),
    rate numeric(15,4),
    description character varying(40),
    free_local smallint,
    free_national smallint,
    free_international smallint,
    national_roaming smallint,
    international_roaming smallint,
    discount_id smallint
);


ALTER TABLE public.bill_plans OWNER TO postgres;

--
-- Name: charges; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE public.charges (
    charge_id smallint,
    charge_rate numeric(15,4),
    description character varying(40)
);


ALTER TABLE public.charges OWNER TO postgres;

--
-- Name: discounts; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE public.discounts (
    discount_id smallint,
    level smallint,
    flat_rate numeric(15,4),
    percentage smallint
);


ALTER TABLE public.discounts OWNER TO postgres;

--
-- Name: invoice; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE public.invoice (
    account_no integer,
    bill_no integer,
    statement_date date,
    from_date date,
    to_date date,
    total numeric(16,4),
    tax numeric(16,4),
    total_invoice numeric(16,4),
    payment_date date
);


ALTER TABLE public.invoice OWNER TO postgres;

--
-- Name: invoice_details; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE public.invoice_details (
    bill_no integer,
    subscr_no integer,
    type_code smallint,
    subtype_code smallint,
    amount numeric(15,4),
    duration integer
);


ALTER TABLE public.invoice_details OWNER TO postgres;

--
-- Name: services; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE public.services (
    subscr_no integer,
    external_id character varying(15),
    service_type smallint,
    bill_plan_id smallint,
    account_no integer
);


ALTER TABLE public.services OWNER TO postgres;

--
-- Name: usages; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE public.usages (
    type_id_usg smallint,
    description character varying(40),
    rate numeric(15,4)
);


ALTER TABLE public.usages OWNER TO postgres;

--
-- Data for Name: accounts; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.accounts (account_no, external_id, first_name, last_name, address, city, state, country, payment_method, bank_account, bank_ref_no, expiry_date) FROM stdin;
1	22561375	Jose	Barraquer	111, Sec 4, Vihar	New Delhi	New Delhi	India	Cash			\N
2	19162029	ngel	Cabrera	1234, Bangalore	Bangalore	Karnataka	India	Online	11098786		\N
3	11636205	Julio	Baviera	442, Street 2	Bangalore	Karnataka	India	CreditCard	989878791730		2045-02-03
4	9501220	Avelino	Cansss	56, Sec 4, Gurgaon	New Delhi	New Delhi	India	Cash			\N
5	21448363	Fausto	Elhyar 	789 Gaziabad	New Delhi	New Delhi	India	Online	11098896		\N
6	15216227	Antoni	Gimbernat	123 Nehruplace	New Delhi	New Delhi	India	CreditCard	989878791778		2045-09-03
7	16782477	Abdul	Kalam	456 Okla	New Delhi	New Delhi	India	Cash			\N
8	24528672	Emilio	Linares 	092- 2nd floor South Ex	New Delhi	New Delhi	India	Online	11098791		\N
9	23688540	Severo	Ochoa	78- 1st Floor Lajpat	New Delhi	New Delhi	India	CreditCard	98987878761		2085-02-03
10	21480443	Isaac	Peral	890- Cyber Hub	Noida	UP	India	Online	156098786		\N
\.


--
-- Data for Name: bill_plans; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.bill_plans (bill_plan_id, plan_name, rate, description, free_local, free_national, free_international, national_roaming, international_roaming, discount_id) FROM stdin;
100	BTELP	3.0000	Basic Telephone Package	50	50	30	20	20	\N
101	PRES	10.0000	Plan Base Residencial	60	60	40	30	30	\N
102	MPIA2	15.0000	Plan Integrated Voice +Data 100 MB	100	100	80	50	20	\N
103	PEMP	20.0000	Plan Base Empresas	150	150	90	60	30	\N
104	SSTAC	30.0000	Plan Smart S	200	200	100	80	30	\N
104	SSTAC	30.0000	Plan Smart S	200	200	100	80	30	\N
105	TVOP	20.0000	Plan TV	\N	\N	\N	\N	\N	302
\.


--
-- Data for Name: charges; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.charges (charge_id, charge_rate, description) FROM stdin;
200	20.0000	Leased Line Installation Charge
201	10.0000	Installation of Telephone
202	30.0000	Termination of Telephone
203	20.0000	Finance charge
204	10.0000	Try&buy Find&GO
205	30.0000	TV installation charge
\.


--
-- Data for Name: discounts; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.discounts (discount_id, level, flat_rate, percentage) FROM stdin;
300	2	10.0000	\N
301	2	20.0000	\N
302	2	\N	5
303	1	15.0000	\N
304	1	25.0000	\N
305	1	\N	10
\.


--
-- Data for Name: invoice; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.invoice (account_no, bill_no, statement_date, from_date, to_date, total, tax, total_invoice, payment_date) FROM stdin;
1	111	2020-01-02	2019-12-01	2019-12-31	330.0000	69.3000	399.3000	2020-01-20
1	112	2020-02-02	2020-01-01	2020-01-31	400.0000	84.0000	484.0000	2020-02-20
1	113	2020-03-02	2020-02-01	2020-02-29	300.0000	63.0000	363.0000	2020-03-01
1	114	2020-04-02	2020-03-01	2020-03-31	500.0000	105.0000	605.0000	2020-04-20
2	211	2020-01-02	2019-12-01	2019-12-31	400.0000	84.0000	484.0000	2020-01-20
3	311	2020-01-02	2019-12-01	2019-12-31	200.0000	42.0000	242.0000	2020-01-20
4	411	2020-01-02	2019-12-01	2019-12-31	500.0000	105.0000	605.0000	2020-01-20
5	511	2020-01-02	2019-12-01	2019-12-31	1000.0000	210.0000	1210.0000	2020-01-20
6	611	2020-01-02	2019-12-01	2019-12-31	330.0000	69.3000	399.3000	2020-01-20
7	711	2020-01-02	2019-12-01	2019-12-31	189.0000	39.6900	228.6900	2020-01-20
8	811	2020-01-02	2019-12-01	2019-12-31	907.0000	190.4700	1097.4700	2020-01-20
9	911	2020-01-02	2019-12-01	2019-12-31	876.0000	183.9600	1059.9600	2020-01-20
10	1011	2020-01-02	2019-12-01	2019-12-31	190.0000	39.9000	229.9000	2020-01-20
\.


--
-- Data for Name: invoice_details; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.invoice_details (bill_no, subscr_no, type_code, subtype_code, amount, duration) FROM stdin;
111	500	2	100	3.0000	\N
111	500	3	201	10.0000	\N
111	500	5	300	10.0000	\N
111	500	7	400	15.0000	100
111	500	7	401	18.0000	30
111	500	7	402	15.0000	10
111	501	2	100	3.0000	\N
111	501	3	201	10.0000	\N
111	501	7	400	3.0000	20
\.


--
-- Data for Name: services; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.services (subscr_no, external_id, service_type, bill_plan_id, account_no) FROM stdin;
500	663190506	1	100	1
501	670990298	1	100	2
502	687452620	1	101	3
503	687452608	1	101	4
504	687465118	1	102	5
505	662121799	1	102	6
506	673361650	1	103	7
507	662121799	1	103	8
508	687466760	1	104	9
509	687467425	1	104	10
510	663193677	1	100	1
511	699933559	1	100	2
512	679571512	1	101	3
513	687466760	1	101	5
514	673361650	1	102	5
515	662121799	1	103	6
516	V21614240	2	105	6
517	680880298	1	103	7
518	677452720	1	104	8
519	687487298	1	104	9
520	V29014240	2	105	10
521	687462385	1	100	1
522	663198197	1	100	2
\.


--
-- Data for Name: usages; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.usages (type_id_usg, description, rate) FROM stdin;
400	Local	0.1500
401	National	0.6000
402	International	1.5000
403	National roaming	0.4000
404	International roaming	8.0000
\.


--
-- Name: SCHEMA public; Type: ACL; Schema: -; Owner: postgres
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM postgres;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO PUBLIC;


--
-- PostgreSQL database dump complete
--

\connect videobill2

SET default_transaction_read_only = off;

--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'SQL_ASCII';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET client_min_messages = warning;

--
-- Name: plpgsql; Type: EXTENSION; Schema: -; Owner: 
--

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;


--
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


--
-- Name: SCHEMA public; Type: ACL; Schema: -; Owner: postgres
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM postgres;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO PUBLIC;


--
-- PostgreSQL database dump complete
--

--
-- PostgreSQL database cluster dump complete
--


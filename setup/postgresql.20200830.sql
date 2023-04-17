--
-- PostgreSQL database dump
--

-- Dumped from database version 10.12
-- Dumped by pg_dump version 10.12

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

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
-- Name: accounts; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.accounts (
    account_no integer,
    external_id character varying(10),
    first_name character varying(30),
    last_name character varying(30),
    address character varying(40),
    city character varying(30),
    state character varying(30),
    zip_code integer,
    country character varying(30),
    payment_method character varying(30),
    bank_account character varying(30),
    bank_ref_no character varying(30),
    expiry_date date
);


ALTER TABLE public.accounts OWNER TO postgres;

--
-- Name: bill_plans; Type: TABLE; Schema: public; Owner: postgres
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
-- Name: charges; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.charges (
    charge_id smallint,
    charge_rate numeric(15,4),
    description character varying(40)
);


ALTER TABLE public.charges OWNER TO postgres;

--
-- Name: discounts; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.discounts (
    discount_id smallint,
    level smallint,
    flat_rate numeric(15,4),
    percentage smallint
);


ALTER TABLE public.discounts OWNER TO postgres;

--
-- Name: invoice; Type: TABLE; Schema: public; Owner: postgres
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
-- Name: invoice_details; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.invoice_details (
    bill_no integer,
    subscr_no integer,
    type_code smallint,
    subtype_code smallint,
    amount numeric(15,4),
    usage_duration integer
);


ALTER TABLE public.invoice_details OWNER TO postgres;

--
-- Name: services; Type: TABLE; Schema: public; Owner: postgres
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
-- Name: usages; Type: TABLE; Schema: public; Owner: postgres
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

COPY public.accounts (account_no, external_id, first_name, last_name, address, city, state, zip_code, country, payment_method, bank_account, bank_ref_no, expiry_date) FROM stdin;
1       22561375        José    Feliciano       Nanclares De OCA, 32,2,B        Madrid  MADRID  28022   España  Cash                    \N
2       19162029        Ángel   Cabrera Nanclares De OCA, 32,2,B        Madrid  MADRID  28022   España  Online  11098786                \N
3       11636205        Julio   Baviera Nanclares De OCA, 32,2,B        Madrid  MADRID  28022   España  CreditCard      989878791730            2045-02-03
\.


--
-- Data for Name: bill_plans; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.bill_plans (bill_plan_id, plan_name, rate, description, free_local, free_national, free_international, national_roaming, international_roaming, discount_id) FROM stdin;
100     BTELP   3.0000  Basic Telephone Package 50      50      30      20      20      \N
101     PRES    10.0000 Plan Base Residencial   60      60      40      30      30      \N
102     MPIA2   15.0000 Plan Integrated Voice +Data 100 MB      100     100     80      50      20      \N
103     PEMP    20.0000 Plan Base Empresas      150     150     90      60      30      \N
104     SSTAC   30.0000 Plan Smart S    200     200     100     80      30      \N
105     TVOP    20.0000 Plan TV \N      \N      \N      \N      \N      302
\.


--
-- Data for Name: charges; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.charges (charge_id, charge_rate, description) FROM stdin;
200     20.0000 Leased Line Installation Charge
201     10.0000 Installation of Telephone
202     30.0000 Termination of Telephone
203     20.0000 Finance charge
204     10.0000 Try&buy Find&GO
205     30.0000 TV installation charge
\.


--
-- Data for Name: discounts; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.discounts (discount_id, level, flat_rate, percentage) FROM stdin;
300     2       10.0000 \N
301     2       20.0000 \N
302     2       \N      5
303     1       15.0000 \N
304     1       25.0000 \N
305     1       \N      10
\.


--
-- Data for Name: invoice; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.invoice (account_no, bill_no, statement_date, from_date, to_date, total, tax, total_invoice, payment_date) FROM stdin;
1       111     2020-04-02      2020-03-01      2020-03-31      149.0000        22.3500 171.3500        2020-04-20
1       112     2020-05-02      2020-04-01      2020-04-30      96.7500 14.5100 111.2600        2020-05-20
1       113     2020-06-02      2020-05-01      2020-05-31      124.2000        18.6300 142.8300        \N
1       114     2020-07-02      2020-06-01      2020-06-30      121.3000        18.2000 139.5000        \N
2       211     2020-06-02      2020-05-01      2020-05-31      213.4000        32.0100 245.4100        2020-06-20
2       212     2020-07-02      2020-06-01      2020-06-30      161.9500        24.2900 186.2400        \N
3       311     2020-06-02      2020-05-01      2020-05-31      149.0000        22.3500 171.3500        2020-06-20
3       312     2020-07-02      2020-06-01      2020-06-30      76.7500 11.5100 88.2600 \N
\.


--
-- Data for Name: invoice_details; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.invoice_details (bill_no, subscr_no, type_code, subtype_code, amount, usage_duration) FROM stdin;
111     500     2       100     3.0000  \N
111     500     3       201     10.0000 \N
111     500     5       300     10.0000 \N
111     500     7       400     15.0000 100
111     500     7       401     18.0000 30
111     500     7       402     15.0000 10
111     501     2       105     20.0000 \N
111     501     3       205     30.0000 \N
111     502     2       102     15.0000 \N
111     502     3       201     10.0000 \N
111     502     7       400     3.0000  20
112     500     2       100     3.0000  \N
112     500     5       300     10.0000 \N
112     500     7       400     7.5000  50
112     500     7       401     9.0000  15
112     500     7       402     27.0000 18
112     501     2       105     20.0000 \N
112     502     2       102     15.0000 \N
112     502     7       400     5.2500  35
113     500     2       100     3.0000  \N
113     500     5       300     10.0000 \N
113     500     7       400     22.5000 150
113     500     7       401     10.8000 18
113     500     7       402     33.0000 22
113     501     2       105     20.0000 \N
113     502     2       102     15.0000 \N
113     502     7       400     9.9000  66
114     500     2       100     3.0000  \N
114     500     5       300     10.0000 \N
114     500     7       400     20.2500 135
114     500     7       401     13.8000 23
114     500     7       402     52.5000 35
114     501     2       105     20.0000 \N
114     502     2       102     15.0000 \N
114     502     7       400     6.7500  45
211     503     2       100     3.0000  \N
211     503     3       201     10.0000 \N
211     503     5       300     10.0000 \N
211     503     7       400     13.3500 89
211     503     7       401     45.6000 76
211     503     7       402     67.5000 45
211     504     2       102     15.0000 \N
211     504     3       201     10.0000 \N
211     504     7       400     4.2000  28
211     505     2       102     15.0000 \N
211     505     3       201     10.0000 \N
211     505     7       400     9.7500  65
212     503     2       100     3.0000  \N
212     503     5       300     10.0000 \N
212     503     7       400     13.3500 89
212     503     7       401     45.6000 76
212     503     7       402     67.5000 45
212     504     2       102     15.0000 \N
212     504     7       400     4.2000  28
212     505     2       102     15.0000 \N
212     505     7       400     9.7500  65
311     506     2       100     3.0000  \N
311     506     3       201     10.0000 \N
311     506     5       300     10.0000 \N
311     506     7       400     15.0000 100
311     506     7       401     18.0000 30
311     506     7       402     15.0000 10
311     507     2       102     15.0000 \N
311     507     3       201     10.0000 \N
311     507     7       400     3.0000  20
311     508     2       105     20.0000 \N
311     508     3       205     30.0000 \N
312     506     2       100     3.0000  \N
312     506     5       300     10.0000 \N
312     506     7       400     7.5000  50
312     506     7       401     9.0000  15
312     506     7       402     27.0000 18
312     507     2       102     15.0000 \N
312     507     7       400     5.2500  35
312     508     2       105     20.0000 \N
\.


--
-- Data for Name: services; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.services (subscr_no, external_id, service_type, bill_plan_id, account_no) FROM stdin;
500     663190506       1       100     1
501     V70990298       3       105     1
502     999452620       2       101     1
503     687452608       1       101     2
504     687465118       2       101     2
505     662121799       1       102     2
506     673361650       1       103     3
507     662121797       1       104     3
508     V87466760       3       105     3
\.


--
-- Data for Name: usages; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.usages (type_id_usg, description, rate) FROM stdin;
400     Local   0.1500
401     National        0.6000
402     International   1.5000
403     National roaming        0.4000
404     International roaming   8.0000
\.


--
-- PostgreSQL database dump complete
--

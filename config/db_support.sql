--
-- PostgreSQL database dump
--

\restrict iEKjnTzBQYBiLNmcoQhg6GfG82gfVJPWyzrYB5zkLbQsAAJvxbOLekYhybBRWKL

-- Dumped from database version 18.1
-- Dumped by pg_dump version 18.1

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: ticket_category; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE public.ticket_category AS ENUM (
    'Instalacion',
    'Software',
    'Hardware',
    'Internet',
    'Otro'
);


ALTER TYPE public.ticket_category OWNER TO postgres;

--
-- Name: ticket_status; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE public.ticket_status AS ENUM (
    'Pendiente',
    'En camino',
    'En proceso',
    'Atendido',
    'Rechazado'
);


ALTER TYPE public.ticket_status OWNER TO postgres;

--
-- Name: user_role; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE public.user_role AS ENUM (
    'admin',
    'tecnico'
);


ALTER TYPE public.user_role OWNER TO postgres;

--
-- Name: asignar_tecnico(integer, integer, integer); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.asignar_tecnico(p_ticket_id integer, p_technician_id integer, p_admin_id integer) RETURNS void
    LANGUAGE plpgsql
    AS $$
BEGIN
    -- Actualizar ticket
    UPDATE tickets
    SET 
        technician_id = p_technician_id,
        status = 'En camino'
    WHERE id = p_ticket_id;

    -- Historial
    INSERT INTO ticket_history(ticket_id, status, changed_by, comment)
    VALUES (p_ticket_id, 'En camino', p_admin_id, 'Técnico asignado');
END;
$$;


ALTER FUNCTION public.asignar_tecnico(p_ticket_id integer, p_technician_id integer, p_admin_id integer) OWNER TO postgres;

--
-- Name: create_ticket(integer, character varying, character varying, text); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.create_ticket(p_user_id integer, p_category character varying, p_title character varying, p_description text) RETURNS integer
    LANGUAGE plpgsql
    AS $$
DECLARE
    new_ticket_id INT;
BEGIN
    -- Crear ticket (ya queda en Pendiente por defecto)
    INSERT INTO tickets(user_id, category, title, description)
    VALUES (p_user_id, p_category::ticket_category, p_title, p_description)
    RETURNING id INTO new_ticket_id;

    -- Historial (lo hace un admin por defecto o sistema → usamos NULL o 1 si tienes admin fijo)
    INSERT INTO ticket_history(ticket_id, status, changed_by, comment)
    VALUES (new_ticket_id, 'Pendiente', NULL, 'Ticket creado');

    RETURN new_ticket_id;
END;
$$;


ALTER FUNCTION public.create_ticket(p_user_id integer, p_category character varying, p_title character varying, p_description text) OWNER TO postgres;

--
-- Name: create_trabajador(character varying, character varying, character varying, character varying, character varying, character varying, integer, text); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.create_trabajador(p_role character varying, p_dni character varying, p_first_name character varying, p_last_name character varying, p_email character varying, p_phone character varying, p_office_id integer, p_password text) RETURNS void
    LANGUAGE plpgsql
    AS $$
BEGIN
    INSERT INTO trabajadores(role, dni, first_name, last_name, email, phone, office_id, password)
    VALUES (p_role, p_dni, p_first_name, p_last_name, p_email, p_phone, p_office_id, p_password);
END;
$$;


ALTER FUNCTION public.create_trabajador(p_role character varying, p_dni character varying, p_first_name character varying, p_last_name character varying, p_email character varying, p_phone character varying, p_office_id integer, p_password text) OWNER TO postgres;

--
-- Name: create_usuario(character varying, character varying, character varying, character varying, character varying, integer, text); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.create_usuario(p_dni character varying, p_first_name character varying, p_last_name character varying, p_email character varying, p_phone character varying, p_office_id integer, p_password text) RETURNS void
    LANGUAGE plpgsql
    AS $$
BEGIN
    INSERT INTO usuarios(dni, first_name, last_name, email, phone, office_id, password)
    VALUES (p_dni, p_first_name, p_last_name, p_email, p_phone, p_office_id, p_password);
END;
$$;


ALTER FUNCTION public.create_usuario(p_dni character varying, p_first_name character varying, p_last_name character varying, p_email character varying, p_phone character varying, p_office_id integer, p_password text) OWNER TO postgres;

--
-- Name: generate_ticket_id(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.generate_ticket_id() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
    prefix_ym INT;
    max_id INT;
BEGIN
    -- Obtenemos el año y mes actual en formato YYYYMM (ej: 202604)
    prefix_ym := to_char(CURRENT_TIMESTAMP, 'YYYYMM')::INT;
    
    -- Buscamos el ticket_id máximo que empiece con este prefijo.
    SELECT MAX(id) INTO max_id
    FROM tickets
    WHERE id::TEXT LIKE (prefix_ym::TEXT || '%');
    
    IF max_id IS NULL THEN
        NEW.id := (prefix_ym::TEXT || '001')::INT;
    ELSE
        NEW.id := max_id + 1;
    END IF;
    
    RETURN NEW;
END;
$$;


ALTER FUNCTION public.generate_ticket_id() OWNER TO postgres;

--
-- Name: login_user(character varying, text); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.login_user(p_dni character varying, p_password text) RETURNS TABLE(id integer, role character varying, first_name character varying, last_name character varying)
    LANGUAGE plpgsql
    AS $$
BEGIN
    RETURN QUERY

    -- TRABAJADORES (admin / tecnico)
    SELECT 
        t.id,
        t.role::VARCHAR,
        t.first_name,
        t.last_name
    FROM trabajadores t
    WHERE t.dni = p_dni
      AND t.password = p_password

    UNION

    -- USUARIOS (clientes)
    SELECT 
        u.id,
        'usuario' AS role,
        u.first_name,
        u.last_name
    FROM usuarios u
    WHERE u.dni = p_dni
      AND u.password = p_password;

END;
$$;


ALTER FUNCTION public.login_user(p_dni character varying, p_password text) OWNER TO postgres;

--
-- Name: search_office(character varying); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.search_office(p_name character varying) RETURNS TABLE(id integer, name character varying, location character varying, location_detail text)
    LANGUAGE plpgsql
    AS $$
BEGIN
    RETURN QUERY
    SELECT o.id, o.name, o.location, o.location_detail
    FROM oficina o
    WHERE o.name ILIKE '%' || p_name || '%';
END;
$$;


ALTER FUNCTION public.search_office(p_name character varying) OWNER TO postgres;

--
-- Name: update_ticket_status(integer, integer, character varying, text); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.update_ticket_status(p_ticket_id integer, p_technician_id integer, p_status character varying, p_comment text) RETURNS void
    LANGUAGE plpgsql
    AS $$
BEGIN
    -- Actualizar ticket
    UPDATE tickets
    SET 
        status = p_status::ticket_status,
        attended_at = CASE 
            WHEN p_status = 'Atendido' THEN NOW()
            ELSE attended_at
        END
    WHERE id = p_ticket_id
      AND technician_id = p_technician_id;

    -- Historial
    INSERT INTO ticket_history(ticket_id, status, changed_by, comment)
    VALUES (p_ticket_id, p_status::ticket_status, p_technician_id, p_comment);
END;
$$;


ALTER FUNCTION public.update_ticket_status(p_ticket_id integer, p_technician_id integer, p_status character varying, p_comment text) OWNER TO postgres;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: oficina; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.oficina (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    location character varying(150),
    location_detail text,
    is_active boolean DEFAULT true
);


ALTER TABLE public.oficina OWNER TO postgres;

--
-- Name: oficina_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.oficina_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.oficina_id_seq OWNER TO postgres;

--
-- Name: oficina_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.oficina_id_seq OWNED BY public.oficina.id;


--
-- Name: ticket_files; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.ticket_files (
    id integer NOT NULL,
    ticket_id integer NOT NULL,
    file_path text NOT NULL,
    uploaded_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.ticket_files OWNER TO postgres;

--
-- Name: ticket_files_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.ticket_files_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.ticket_files_id_seq OWNER TO postgres;

--
-- Name: ticket_files_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.ticket_files_id_seq OWNED BY public.ticket_files.id;


--
-- Name: ticket_history; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.ticket_history (
    id integer NOT NULL,
    ticket_id integer NOT NULL,
    status public.ticket_status NOT NULL,
    comment text,
    changed_by integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.ticket_history OWNER TO postgres;

--
-- Name: ticket_history_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.ticket_history_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.ticket_history_id_seq OWNER TO postgres;

--
-- Name: ticket_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.ticket_history_id_seq OWNED BY public.ticket_history.id;


--
-- Name: tickets; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.tickets (
    id integer NOT NULL,
    user_id integer NOT NULL,
    technician_id integer,
    office_id integer,
    category public.ticket_category,
    title character varying(200) NOT NULL,
    description text,
    tech_comment text,
    status public.ticket_status DEFAULT 'Pendiente'::public.ticket_status NOT NULL,
    attended_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.tickets OWNER TO postgres;

--
-- Name: tickets_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.tickets_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tickets_id_seq OWNER TO postgres;

--
-- Name: tickets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.tickets_id_seq OWNED BY public.tickets.id;


--
-- Name: trabajadores; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.trabajadores (
    id integer NOT NULL,
    role public.user_role NOT NULL,
    dni character varying(20) NOT NULL,
    first_name character varying(100) NOT NULL,
    last_name character varying(100) NOT NULL,
    email character varying(100),
    phone character varying(20),
    office_id integer,
    password text NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    is_active boolean DEFAULT true
);


ALTER TABLE public.trabajadores OWNER TO postgres;

--
-- Name: trabajadores_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.trabajadores_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.trabajadores_id_seq OWNER TO postgres;

--
-- Name: trabajadores_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.trabajadores_id_seq OWNED BY public.trabajadores.id;


--
-- Name: usuarios; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.usuarios (
    id integer NOT NULL,
    dni character varying(20) NOT NULL,
    first_name character varying(100) NOT NULL,
    last_name character varying(100) NOT NULL,
    email character varying(100),
    phone character varying(20),
    office_id integer,
    password text NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    is_active boolean DEFAULT true
);


ALTER TABLE public.usuarios OWNER TO postgres;

--
-- Name: usuarios_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.usuarios_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.usuarios_id_seq OWNER TO postgres;

--
-- Name: usuarios_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.usuarios_id_seq OWNED BY public.usuarios.id;


--
-- Name: oficina id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.oficina ALTER COLUMN id SET DEFAULT nextval('public.oficina_id_seq'::regclass);


--
-- Name: ticket_files id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ticket_files ALTER COLUMN id SET DEFAULT nextval('public.ticket_files_id_seq'::regclass);


--
-- Name: ticket_history id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ticket_history ALTER COLUMN id SET DEFAULT nextval('public.ticket_history_id_seq'::regclass);


--
-- Name: tickets id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tickets ALTER COLUMN id SET DEFAULT nextval('public.tickets_id_seq'::regclass);


--
-- Name: trabajadores id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.trabajadores ALTER COLUMN id SET DEFAULT nextval('public.trabajadores_id_seq'::regclass);


--
-- Name: usuarios id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.usuarios ALTER COLUMN id SET DEFAULT nextval('public.usuarios_id_seq'::regclass);


--
-- Data for Name: oficina; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.oficina (id, name, location, location_detail, is_active) FROM stdin;
1	Gerencia General	Edificio Principal	Piso 1	t
2	Recursos Humanos	Edificio Administrativo	Piso 2	t
3	Contabilidad	Edificio Financiero	Piso 3	t
4	Tecnologías de la Información	Edificio TI	Piso 2 - Oficina 201	t
5	Logística	Almacén Central	Zona Industrial	t
\.


--
-- Data for Name: ticket_files; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.ticket_files (id, ticket_id, file_path, uploaded_at) FROM stdin;
1	202604001	uploads/ticket1_img1.jpg	2026-04-13 18:39:14.950115
2	202604001	uploads/ticket1_img2.jpg	2026-04-13 18:39:14.950115
3	202604002	uploads/ticket2_img1.jpg	2026-04-13 18:39:14.950115
4	202604003	uploads/ticket3_img1.jpg	2026-04-13 18:39:14.950115
5	202604004	uploads/ticket4_img1.jpg	2026-04-13 18:39:14.950115
6	202604005	uploads/ticket5_img1.jpg	2026-04-13 18:39:14.950115
\.


--
-- Data for Name: ticket_history; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.ticket_history (id, ticket_id, status, comment, changed_by, created_at) FROM stdin;
1	202604001	Pendiente	Ticket creado	1	2026-04-13 18:39:14.950115
2	202604001	En proceso	Asignado a técnico	1	2026-04-13 18:39:14.950115
3	202604001	Atendido	Solucionado	2	2026-04-13 18:39:14.950115
4	202604002	Pendiente	Ticket creado	1	2026-04-13 18:39:14.950115
5	202604002	En proceso	Asignado a técnico	1	2026-04-13 18:39:14.950115
6	202604003	Pendiente	Ticket creado	1	2026-04-13 18:39:14.950115
7	202604004	Pendiente	Ticket creado	1	2026-04-13 18:39:14.950115
8	202604004	Rechazado	Solicitud fuera de alcance	1	2026-04-13 18:39:14.950115
9	202604005	Pendiente	Ticket creado	1	2026-04-13 18:39:14.950115
10	202604005	En proceso	Asignado a técnico	1	2026-04-13 18:39:14.950115
11	202604001	Atendido	El administrador reescribió los detalles del ticket	1	2026-04-13 21:32:40.738583
12	202604006	Pendiente	Ticket creado	\N	2026-04-14 10:00:52.139519
13	202604006	En camino	Técnico asignado	1	2026-04-14 10:01:28.474453
14	202604007	Pendiente	Ticket creado	\N	2026-04-14 10:02:51.60071
15	202604007	En camino	Técnico asignado	1	2026-04-14 10:03:09.282257
16	202604006	En proceso	El técnico actualizó el estado a En proceso	2	2026-04-14 10:03:30.136889
17	202604007	En proceso	El técnico actualizó el estado a En proceso	2	2026-04-14 10:03:52.743275
18	202604007	Atendido	El técnico actualizó el estado a Atendido	2	2026-04-14 10:04:06.04921
19	202604006	Atendido	El técnico actualizó el estado a Atendido	2	2026-04-14 10:04:57.542737
20	202604008	Pendiente	Ticket creado desde el portal público	\N	2026-04-15 11:02:49.506209
21	202604009	Pendiente	Ticket creado desde el portal público	\N	2026-04-15 11:33:12.711743
22	202604008	En camino	Técnico asignado	1	2026-04-15 11:43:45.891695
23	202604008	En proceso	El técnico actualizó el estado a En proceso	2	2026-04-15 11:45:26.119743
24	202604008	Atendido	El técnico actualizó el estado a Atendido	2	2026-04-15 11:45:40.1989
25	202604009	En camino	Técnico asignado	1	2026-04-21 18:37:59.835681
26	202605000	Pendiente	Ticket creado (Admin)	1	2026-05-04 16:01:34.185017
27	202605001	Pendiente	Ticket creado (Admin)	1	2026-05-04 16:02:59.528215
28	202605002	Pendiente	Ticket creado (Admin)	1	2026-05-04 16:03:33.414968
\.


--
-- Data for Name: tickets; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.tickets (id, user_id, technician_id, office_id, category, title, description, tech_comment, status, attended_at, created_at) FROM stdin;
202604002	2	3	3	Hardware	PC no enciende	El equipo no responde al presionar el botón		En proceso	\N	2026-04-13 18:39:14.950115
202604003	3	\N	5	Internet	Sin conexión	No hay acceso a internet en mi área		Pendiente	\N	2026-04-13 18:39:14.950115
202604004	4	4	2	Instalacion	Instalar impresora	Necesito instalar impresora nueva	No es política del área instalar impresoras personales.	Rechazado	2026-04-13 18:39:14.950115	2026-04-13 18:39:14.950115
202604005	1	2	2	Software	Office no funciona	Word se cierra automáticamente		En proceso	\N	2026-04-13 18:39:14.950115
202604001	1	2	2	Software	Error en sistema contable	No puedo acceder al sistema contable	Se reinició el servidor de la DB.	Atendido	2026-04-13 18:39:14.950115	2026-04-13 18:39:14.950115
202604007	1	2	3	Software	asdasdasd	asdasdasd		Atendido	2026-04-14 10:04:06.039577	2026-04-14 10:02:51.595673
202604006	1	2	3	Otro	adasjdlasjld	asdalsdjlk	mensaje asd	Atendido	2026-04-14 10:04:57.537344	2026-04-14 10:00:52.120065
202604008	5	2	3	Internet	web	aaaddsd	ok	Atendido	2026-04-15 11:45:40.193168	2026-04-15 11:02:49.506209
202604009	7	2	\N	Software	X	Y	\N	En camino	\N	2026-04-15 11:33:12.711743
202605000	1	\N	2	Software	office xd	123	\N	Pendiente	\N	2026-05-04 16:01:34.185017
202605001	1	\N	2	Instalacion	asd	zxc	\N	Pendiente	\N	2026-05-04 16:02:59.528215
202605002	2	\N	3	Hardware	adaswvwfe	qefq3r	\N	Pendiente	\N	2026-05-04 16:03:33.414968
\.


--
-- Data for Name: trabajadores; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.trabajadores (id, role, dni, first_name, last_name, email, phone, office_id, password, created_at, is_active) FROM stdin;
4	tecnico	70000004	Jorge	Perez	jorge.ti@empresa.com	987654324	4	123456	2026-04-13 18:39:14.950115	t
2	tecnico	70000002	Luis	Quispe	luis.ti@empresa.com	987654322	4	123456	2026-04-13 18:39:14.950115	t
3	tecnico	70000003	Ana	Flores	ana.ti@empresa.com	987654323	4	123456	2026-04-13 18:39:14.950115	t
1	admin	70000001	Carlos	Ramirez	admin1@empresa.com	999999999	4	123456	2026-04-13 18:39:14.950115	t
\.


--
-- Data for Name: usuarios; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.usuarios (id, dni, first_name, last_name, email, phone, office_id, password, created_at, is_active) FROM stdin;
3	70000007	Lucia	Torres	lucia@empresa.com	987654327	5	123456	2026-04-13 18:39:14.950115	t
4	70000008	Jose	Vargas	jose@empresa.com	987654328	2	123456	2026-04-13 18:39:14.950115	t
5	70000009	Pedro	Aquino	\N	999999999	3	70000009	2026-04-15 11:02:49.506209	t
6	99999999	Test	Test	\N	123	\N	99999999	2026-04-15 11:32:46.451358	t
7	99999998	Jane	Doe	\N	333	\N	99999998	2026-04-15 11:33:12.711743	t
1	70000005	Maria	Lopez	maria@empresa.com	987654325	2	123456	2026-04-13 18:39:14.950115	t
2	70000006	Pedro	Gomez	pedro@empresa.com	987654326	3	123456	2026-04-13 18:39:14.950115	t
\.


--
-- Name: oficina_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.oficina_id_seq', 5, true);


--
-- Name: ticket_files_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.ticket_files_id_seq', 6, true);


--
-- Name: ticket_history_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.ticket_history_id_seq', 28, true);


--
-- Name: tickets_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.tickets_id_seq', 12, true);


--
-- Name: trabajadores_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.trabajadores_id_seq', 4, true);


--
-- Name: usuarios_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.usuarios_id_seq', 7, true);


--
-- Name: oficina oficina_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.oficina
    ADD CONSTRAINT oficina_pkey PRIMARY KEY (id);


--
-- Name: ticket_files ticket_files_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ticket_files
    ADD CONSTRAINT ticket_files_pkey PRIMARY KEY (id);


--
-- Name: ticket_history ticket_history_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ticket_history
    ADD CONSTRAINT ticket_history_pkey PRIMARY KEY (id);


--
-- Name: tickets tickets_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_pkey PRIMARY KEY (id);


--
-- Name: trabajadores trabajadores_dni_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.trabajadores
    ADD CONSTRAINT trabajadores_dni_key UNIQUE (dni);


--
-- Name: trabajadores trabajadores_email_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.trabajadores
    ADD CONSTRAINT trabajadores_email_key UNIQUE (email);


--
-- Name: trabajadores trabajadores_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.trabajadores
    ADD CONSTRAINT trabajadores_pkey PRIMARY KEY (id);


--
-- Name: usuarios usuarios_dni_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_dni_key UNIQUE (dni);


--
-- Name: usuarios usuarios_email_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_email_key UNIQUE (email);


--
-- Name: usuarios usuarios_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_pkey PRIMARY KEY (id);


--
-- Name: idx_tickets_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_tickets_status ON public.tickets USING btree (status);


--
-- Name: idx_tickets_technician; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_tickets_technician ON public.tickets USING btree (technician_id);


--
-- Name: idx_tickets_user; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_tickets_user ON public.tickets USING btree (user_id);


--
-- Name: tickets trigger_generate_ticket_id; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER trigger_generate_ticket_id BEFORE INSERT ON public.tickets FOR EACH ROW EXECUTE FUNCTION public.generate_ticket_id();


--
-- Name: ticket_files ticket_files_ticket_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ticket_files
    ADD CONSTRAINT ticket_files_ticket_id_fkey FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE CASCADE;


--
-- Name: ticket_history ticket_history_changed_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ticket_history
    ADD CONSTRAINT ticket_history_changed_by_fkey FOREIGN KEY (changed_by) REFERENCES public.trabajadores(id);


--
-- Name: ticket_history ticket_history_ticket_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ticket_history
    ADD CONSTRAINT ticket_history_ticket_id_fkey FOREIGN KEY (ticket_id) REFERENCES public.tickets(id) ON DELETE CASCADE;


--
-- Name: tickets tickets_office_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_office_id_fkey FOREIGN KEY (office_id) REFERENCES public.oficina(id) ON DELETE SET NULL;


--
-- Name: tickets tickets_technician_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_technician_id_fkey FOREIGN KEY (technician_id) REFERENCES public.trabajadores(id);


--
-- Name: tickets tickets_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.usuarios(id);


--
-- Name: trabajadores trabajadores_office_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.trabajadores
    ADD CONSTRAINT trabajadores_office_id_fkey FOREIGN KEY (office_id) REFERENCES public.oficina(id);


--
-- Name: usuarios usuarios_office_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_office_id_fkey FOREIGN KEY (office_id) REFERENCES public.oficina(id);


--
-- PostgreSQL database dump complete
--

\unrestrict iEKjnTzBQYBiLNmcoQhg6GfG82gfVJPWyzrYB5zkLbQsAAJvxbOLekYhybBRWKL


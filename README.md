# Instalación del proyecto en un entorno local

## Requisitos

Antes de iniciar, asegúrate de tener instalados los siguientes programas:

* **Laragon 2025** (v8.3.0) Link: https://www.filepuma.com/es/download/laragon_8.3.0-54386/
* **PHP 8.3.26** (Incluida en Laragon 2025)
* **MySQL Workbench 8.0.47** Link: https://downloads.mysql.com/archives/workbench/
* **Git** (v2.54.0.windows.1) Link: https://sourceforge.net/projects/git-for-windows.mirror/files/v2.54.0.windows.1/
* **Visual Studio Code** https://code.visualstudio.com/download

## Pasos para la instalación

### 1. Clonar el repositorio

Abre una terminal y ejecuta:

```bash
git clone https://github.com/AquinoPL/helpdesk.git
```

Luego ingresa al directorio del proyecto:

```bash
cd helpdesk/
```

### 2. Copiar el proyecto a Laragon

Si el proyecto no fue clonado directamente en la carpeta de Laragon, cópialo a:

```
C:\laragon\www\
```

### 3. Iniciar Laragon

Abre Laragon e inicia los servicios:

* Apache o Nginx (según la configuración del proyecto)
* MySQL

### 4. Creacion de la base de datos

1. Abrir Mysql Workbench y ejecutar el archivo
```env
config/database-mysql.sql
```

### 5. Configura los datos de conexión a la base de datos

Abrir el archivo con VS Code

```env
config/database.php
```
Ajusta estos valores según tu configuración local

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nombre_base_datos
DB_USERNAME=root
DB_PASSWORD=
```

### 5. Iniciar el proyecto

Acceder a la carpeta del proyecto
```bash
cd helpdesk/
```
Una ves dentro de la carpeta del proyecto ejecutar el proyecto con PHP
```bash
php -S localhost:8080
```

o accede directamente a la carpeta de laragon :

C:\laragon\www\helpdesk

```
http://localhost/helpdesk/index.php
```
> Ajusta estos valores según tu configuración local
---

## Versiones utilizadas durante el desarrollo

| Software        | Versión          |
| --------------- | ---------------- |
| Laragon         | 2025 v8.3.0      |
| PHP             | 8.3.26           |
| MySQL Workbench | 8.0.47           |
| Git             | 2.54.0.windows.1 |

## Notas

* Verificar que Git y PHP esteen añadidas a las variables de entorno del sistema.
* Se recomienda utilizar las mismas versiones de las herramientas para evitar problemas de compatibilidad.
* Verifica que la extensión de PHP requerida por el proyecto esté habilitada.

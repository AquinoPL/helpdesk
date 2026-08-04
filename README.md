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

### 4. Configurar el archivo de entorno


2. Configura los datos de conexión a la base de datos:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nombre_base_datos
DB_USERNAME=root
DB_PASSWORD=
```

> Ajusta estos valores según tu configuración local.

### 5. Crear la base de datos

1. Abre **MySQL Workbench**.
2. Conéctate al servidor MySQL de Laragon.
3. Crea una nueva base de datos con el nombre configurado en el archivo `.env`.

Si el proyecto incluye un archivo `.sql`, impórtalo en la base de datos creada.

### 6. Instalar dependencias

Si el proyecto utiliza Composer, ejecuta:

```bash
composer install
```

Si utiliza Node.js para los recursos frontend, ejecuta:

```bash
npm install
```

### 7. Ejecutar migraciones (si aplica)

```bash
php artisan migrate
```

Si el proyecto tiene datos iniciales:

```bash
php artisan db:seed
```

### 8. Iniciar el proyecto

Si es un proyecto Laravel:

```bash
php artisan serve
```

o accede mediante Laragon:

```
http://nombre-del-proyecto.test
```

---

## Versiones utilizadas durante el desarrollo

| Software        | Versión          |
| --------------- | ---------------- |
| Laragon         | 2025 v8.3.0      |
| PHP             | 8.3.26           |
| MySQL Workbench | 8.0.47           |
| Git             | 2.54.0.windows.1 |

## Notas

* Se recomienda utilizar las mismas versiones de las herramientas para evitar problemas de compatibilidad.
* Verifica que la extensión de PHP requerida por el proyecto esté habilitada.
* Si el proyecto utiliza Composer o Node.js, asegúrate de tenerlos instalados antes de ejecutar los comandos correspondientes.

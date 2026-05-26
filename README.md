# 📖 Himnario Digital v1.5

Aplicación web para la proyección y gestión de un himnario digital, con buscador de himnos, visor tipo proyector para pantalla completa y panel de administración CRUD.

---

## 📋 Tabla de Contenidos

1. [Arquitectura del Proyecto](#-arquitectura-del-proyecto)
2. [Requisitos del Sistema](#-requisitos-del-sistema)
3. [Configuración de la Base de Datos (Docker)](#-configuración-de-la-base-de-datos-docker)
4. [Esquema de la Base de Datos](#-esquema-de-la-base-de-datos)
5. [Inicio Rápido (Linux)](#-inicio-rápido-linux)
6. [Puertos y URLs](#-puertos-y-urls)
7. [Credenciales](#-credenciales)
8. [Estructura del Proyecto](#-estructura-del-proyecto)
9. [Archivos de Base de Datos](#-archivos-de-base-de-datos)
10. [Migración desde SQLite](#-migración-desde-sqlite)
11. [Solución de Problemas](#-solución-de-problemas)

---

## 🏗️ Arquitectura del Proyecto

```
┌─────────────────────────────────────────────────┐
│                   Navegador                       │
│           http://localhost:8001                   │
└──────────────────────┬──────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────┐
│          PHP 8.2 (Contenedor Docker)             │
│         himnario_1.5/ (código fuente)            │
│                                                   │
│  index.php  →  Buscador público                  │
│  presentacion.php  →  Visor proyector            │
│  admin/*.php  →  Panel CRUD                      │
│  login.php  →  Autenticación                     │
│  includes/  →  Conexión DB + Helpers             │
│  css/style.css  →  Sistema de diseño             │
│  js/app.js  →  Lógica JS centralizada            │
└──────────────────────┬──────────────────────────┘
                       │
                       │ Red Docker (mysql_default)
                       │
┌──────────────────────▼──────────────────────────┐
│     MySQL 8.0 (Contenedor Docker)                │
│     Container: mysql_himnario_v1                 │
│     DB: himnario_db                              │
│     13 tablas, 3,867 registros                   │
└─────────────────────────────────────────────────┘
```

### Flujo de datos

1. El usuario accede vía navegador a `http://localhost:8001`
2. El contenedor **php_himnario15** (PHP 8.2) sirve los archivos PHP
3. PHP se conecta al contenedor **mysql_himnario_v1** (MySQL 8.0) usando el nombre del servicio `mysql` como host (resolución interna de Docker)
4. Las consultas SQL usan **prepared statements** para prevenir inyección SQL
5. Las respuestas se renderizan con Bootstrap 5.3 + CSS personalizado

---

## 💻 Requisitos del Sistema

- **Docker** y **Docker Compose** (para la base de datos MySQL)
- **PHP 8.0+** con extensión **mysqli** (solo si se ejecuta fuera de Docker)
- **Git** (para control de versiones)
- Sistema operativo: **Linux** (probado en Ubuntu)

---

## 🐳 Configuración de la Base de Datos (Docker)

### Archivo de configuración

**Ruta:** `/home/melquisedec/docker/mysql/docker-compose.yml`

```yaml
version: '3.9'

services:
  mysql:
    image: mysql:8.0
    container_name: mysql_himnario_v1
    restart: always
    environment:
      MYSQL_ROOT_PASSWORD: rootpassword123
      MYSQL_DATABASE: himnario_db
      MYSQL_USER: himnario_user
      MYSQL_PASSWORD: userpassword123
    ports:
      - "3306:3306"
    volumes:
      - mysql-data:/var/lib/mysql
      - ./init:/docker-entrypoint-initdb.d
    mem_limit: 2g

  adminer:
    image: adminer
    container_name: adminer_himnario
    restart: always
    ports:
      - "8080:8080"
    depends_on:
      - mysql

volumes:
  mysql-data:
```

### Iniciar la base de datos

```bash
cd /home/melquisedec/docker/mysql
docker-compose up -d
```

Esto levanta:
- **MySQL 8.0** en el puerto `3306`
- **Adminer** (gestor gráfico) en el puerto `8080`

### Inicialización automática (init scripts)

Los scripts en `/home/melquisedec/docker/mysql/init/` se ejecutan automáticamente la primera vez que arranca MySQL:

| Archivo | Propósito |
|---|---|
| `01-schema.sql` | Crea las 13 tablas del schema |
| `02-seed-admin.sql` | Inserta el usuario admin por defecto |

> **Nota:** Si necesitas reiniciar desde cero, elimina el volumen: `docker-compose down -v && docker-compose up -d`

---

## 🗄️ Esquema de la Base de Datos

El proyecto usa **MySQL 8.0** con motor **InnoDB** y charset **utf8mb4**. Consta de **13 tablas** con **3,867 registros migrados**.

### Tablas principales

| Tabla | Registros | Propósito |
|---|---|---|
| `himnos` | 425 | Catálogo de himnos |
| `estrofas` | 2,524 | Letras de cada himno (con acordes entre `[corchetes]`) |
| `versiones_pais` | 425 | Versiones de cada himno por país (tonalidad) |
| `paises` | 39 | Países disponibles |
| `categorias` | 4 | Categorías (Adoración, Evangelización, Himnario Oficial, Juvenil) |
| `himno_categoria` | 427 | Relación muchos-a-muchos himnos ↔ categorías |
| `usuarios` | 1 | Usuarios del sistema (admin) |
| `fondos_pantalla` | 2 | Fondos para el visor (Dark, White) |
| `configuracion` | 6 | Configuraciones globales |
| `arreglos_musicales` | 2 | Arreglos musicales personalizados |
| `estrofas_arreglo` | 12 | Estrofas de arreglos musicales |
| `pistas_audio` | 0 | Pistas de audio asociadas a himnos |
| `historial_reproduccion` | 0 | Historial de himnos reproducidos |

### Diagrama de relaciones

```
himnos ──1:N── versiones_pais ──1:N── estrofas
  │                │
  │                └── M:1 ── paises
  │
  └── M:N ── categorias  (a través de himno_categoria)

usuarios ──1:N── arreglos_musicales ──1:N── estrofas_arreglo
                               
himnos ──1:N── pistas_audio
himnos ──1:N── historial_reproduccion
```

### Tipos de campos importantes

- **himnos.tipo**: `1` = Oficial, `2` = Inspirada, `3` = Convención
- **estrofas.tipo**: `ENUM('Coro','Estrofa','Puente','Intro','Final')`
- **usuarios.rol**: `ENUM('Admin','Musico','Visualizador')`
- **fondos_pantalla.tipo**: `ENUM('imagen','color_solido')`

---

## 🚀 Inicio Rápido (Linux)

### Opción 1: Todo en Docker (recomendado)

```bash
# 1. Clonar/ir al proyecto
cd /home/melquisedec/Escritorio/Projects/Personales/himnario_1.5

# 2. Asegurarse de que MySQL esté corriendo
cd /home/melquisedec/docker/mysql
docker-compose up -d

# 3. Crear y ejecutar contenedor PHP
docker run -d \
  --name php_himnario15 \
  -v /home/melquisedec/Escritorio/Projects/Personales/himnario_1.5:/var/www/html \
  -p 8001:80 \
  php:8.2-cli

# 4. Instalar extensión mysqli
docker exec php_himnario15 docker-php-ext-install mysqli

# 5. Conectar a la misma red que MySQL
docker network connect mysql_default php_himnario15

# 6. Reiniciar el contenedor
docker restart php_himnario15

# 7. ¡Abrir en el navegador!
# http://localhost:8001
```

### Opción 2: PHP local + MySQL Docker

```bash
# 1. Iniciar MySQL
cd /home/melquisedec/docker/mysql && docker-compose up -d

# 2. Editar includes/db.php (cambiar host de 'mysql' a '127.0.0.1')
#    porque PHP corre en el host, no dentro de Docker

# 3. Iniciar servidor PHP local
cd /home/melquisedec/Escritorio/Projects/Personales/himnario_1.5
php -S localhost:8001

# 4. ¡Abrir en el navegador!
# http://localhost:8001
```

---

## 🌐 Puertos y URLs

### En producción (con Docker)

| Servicio | URL | Puerto | Contenedor |
|---|---|---|---|
| App Web | `http://localhost:8001` | 8001 | `php_himnario15` |
| Adminer (DB) | `http://localhost:8080` | 8080 | `adminer_himnario` |
| MySQL | `localhost:3306` | 3306 | `mysql_himnario_v1` |

### Red Docker

Los contenedores `php_himnario15` y `mysql_himnario_v1` deben estar conectados a la misma red Docker (`mysql_default`) para que PHP pueda resolver el host `mysql`.

Verificar la conexión de red:
```bash
docker network ls
docker network inspect mysql_default
```

Si el contenedor PHP no está en la red:
```bash
docker network connect mysql_default php_himnario15
```

### Solución de problemas de conexión

| Síntoma | Causa | Solución |
|---|---|---|
| `Connection refused` | PHP no alcanza MySQL | Conectar contenedor a la misma red Docker |
| `Host 'mysql' not found` | Red Docker incorrecta | `docker network connect mysql_default php_himnario15` |
| `Access denied` | Credenciales incorrectas | Verificar `includes/db.php` vs `docker-compose.yml` |

---

## 🔑 Credenciales

### Base de Datos MySQL

| Campo | Valor |
|---|---|
| Host (desde el host) | `127.0.0.1` |
| Host (desde Docker, misma red) | `mysql` |
| Puerto | `3306` |
| Base de datos | `himnario_db` |
| Usuario | `himnario_user` |
| Contraseña | `userpassword123` |
| Root password | `rootpassword123` |

### Adminer (gestor gráfico MySQL)

1. Abrir `http://localhost:8080`
2. Seleccionar **MySQL**
3. **Servidor:** `mysql`
4. **Usuario:** `himnario_user`
5. **Contraseña:** `userpassword123`
6. **Base de datos:** `himnario_db`

### App Web

| Página | URL | Credencial |
|---|---|---|
| Login admin | `http://localhost:8001/login.php` | Usuario: `admin` / Contraseña: `admin123` |
| Buscador público | `http://localhost:8001/index.php` | (acceso libre) |
| Presentación/Visor | `http://localhost:8001/presentacion.php?id={ID}` | (acceso libre) |

---

## 📁 Estructura del Proyecto

```
himnario_1.5/
│
├── index.php                    # Buscador público de himnos (con filtros)
├── login.php                    # Login de administrador (password_hash + usuarios DB)
├── presentacion.php             # Visor tipo proyector para pantalla completa
├── prueba_conexion.php          # Script de diagnóstico de conexión
│
├── admin/
│   ├── index.php                # Dashboard CRUD (lista de himnos)
│   ├── crear.php                # Crear himno (con versiones, categorías, estrofas)
│   ├── editar.php               # Editar himno
│   ├── eliminar.php             # Eliminar himno
│   └── logout.php               # Cerrar sesión
│
├── includes/
│   ├── db.php                   # Conexión a MySQL (configurar aquí)
│   └── funciones.php            # Helpers: obtenerCategorias, obtenerEstrofas, etc.
│
├── css/
│   └── style.css                # Sistema de diseño completo (1,380 líneas)
│
├── js/
│   └── app.js                   # JS modular (ThemeManager, EstrofaManager, etc.)
│
├── scripts/
│   ├── schema.sql               # DDL completo de la base de datos (13 tablas)
│   ├── migrar.php               # Script de migración SQLite → MySQL
│   └── verificar_migracion.php  # Verificación post-migración
│
├── assets/
│   └── fondos/                  # Imágenes y videos de fondo para el visor
│
├── sw.js                        # Service Worker (PWA - offline support)
├── manifest.json                # Manifiesto PWA
│
├── .vscode/
│   └── settings.json            # Configuración de VS Code
│
├── README.md                    # Este archivo
└── Himnario_1.5.sql             # Backup del esquema SQL (opcional)
```

---

## 📦 Archivos de Base de Datos

### Schema SQL completo

**Ruta:** `scripts/schema.sql`

Contiene el DDL completo de las 13 tablas con sus relaciones, índices y constraints. Útil para recrear la base de datos manualmente o como referencia.

```bash
# Aplicar el schema directamente
docker exec -i mysql_himnario_v1 mysql -u himnario_user -puserpassword123 himnario_db < scripts/schema.sql
```

### Script de migración SQLite → MySQL

**Ruta:** `scripts/migrar.php`

Script PHP que migra datos desde el archivo SQLite de la app Flutter HimnarioID_2.0 a MySQL. Se conecta simultáneamente a ambas bases de datos, lee registros del SQLite y los inserta en MySQL respetando el orden de las claves foráneas.

```bash
php scripts/migrar.php
```

### Script de verificación

**Ruta:** `scripts/verificar_migracion.php`

Consulta cada tabla de MySQL y muestra el conteo de registros, además de verificar la integridad referencial (0 registros huérfanos).

### Init scripts de Docker

**Ruta:** `/home/melquisedec/docker/mysql/init/`

Se ejecutan automáticamente al iniciar MySQL por primera vez:
- `01-schema.sql` → Crea todas las tablas
- `02-seed-admin.sql` → Inserta usuario admin

---

## 🔄 Migración desde SQLite

La base de datos fue migrada desde la app Flutter **HimnarioID 2.0** (archivo SQLite en `assets/db/himnario_id.db`).

### Proceso de migración

1. Se creó el schema MySQL con 13 tablas equivalentes
2. Se escribió un script PHP (`scripts/migrar.php`) que:
   - Conecta a SQLite vía PDO
   - Conecta a MySQL vía mysqli
   - Lee datos de SQLite en batches y los inserta en MySQL
   - Usa transacciones para garantizar integridad
   - Filtra registros huérfanos (72 estrofas con FK inválidas)
   - Re-hashea la contraseña del admin con `password_hash(bcrypt)`

### Orden de migración

1. `paises` → 39 registros
2. `himnos` → 425 registros
3. `versiones_pais` → 425 registros
4. `estrofas` → 2,524 registros
5. `categorias` → 4 registros
6. `himno_categoria` → 427 registros
7. `usuarios` → 1 registro (admin / bcrypt)
8. `fondos_pantalla` → 2 registros
9. `configuracion` → 6 registros
10. `arreglos_musicales` → 2 registros
11. `estrofas_arreglo` → 12 registros
12. `pistas_audio` → 0 registros
13. `historial_reproduccion` → 0 registros

---

## 🛠️ Solución de Problemas

### Error: `Connection refused` en db.php

**Causa:** El host `mysql` no es resoluble desde donde corre PHP.

**Soluciones:**

1. **Si PHP corre en Docker** (recomendado):
   ```bash
   docker network connect mysql_default php_himnario15
   docker restart php_himnario15
   ```

2. **Si PHP corre en el host**:
   - Cambiar `$host = 'mysql'` a `$host = '127.0.0.1'` en `includes/db.php`
   - Asegurarse de que MySQL esté corriendo: `docker ps | grep mysql`

### Error: `mysqli extension not found`

```bash
# Si estás dentro del contenedor PHP:
docker exec php_himnario15 docker-php-ext-install mysqli
docker restart php_himnario15

# Si estás en el host:
sudo apt install php-mysqli
```

### Error: Base de datos vacía o no existe

```bash
# Verificar que la BD existe
docker exec mysql_himnario_v1 mysql -u himnario_user -puserpassword123 -e "SHOW DATABASES;"

# Si no existe, ejecutar el schema
docker exec -i mysql_himnario_v1 mysql -u himnario_user -puserpassword123 < scripts/schema.sql

# Si los datos no están, ejecutar migración
php scripts/migrar.php
```

### Error: Puerto 8001 ocupado

```bash
# Cambiar el puerto (ejemplo: 8002)
docker rm -f php_himnario15
docker run -d \
  --name php_himnario15 \
  -v /home/melquisedec/Escritorio/Projects/Personales/himnario_1.5:/var/www/html \
  -p 8002:80 \
  php:8.2-cli
# Luego acceder en http://localhost:8002
```

---

## 👨‍💻 Desarrollo

### Scripts útiles

```bash
# Verificar sintaxis PHP de todos los archivos
php -l index.php
php -l login.php
php -l presentacion.php
php -l admin/*.php
php -l includes/*.php

# Verificar conexión a la base de datos
http://localhost:8001/prueba_conexion.php

# Ver logs del contenedor PHP
docker logs php_himnario15

# Ver logs de MySQL
docker logs mysql_himnario_v1
```

### Tecnologías utilizadas

| Tecnología | Versión | Propósito |
|---|---|---|
| PHP | 8.2 | Backend |
| MySQL | 8.0 | Base de datos |
| Bootstrap | 5.3 | Framework CSS |
| Docker | 29.x | Contenedores |
| Adminer | latest | Gestor DB gráfico |
| PWA | — | Service Worker + Manifest |

---

## 📄 Licencia

Proyecto personal. Todos los derechos reservados.

---

*Última actualización: 26 de mayo de 2026*

# 🐳 Himnario Digital v1.5 — Guía para levantar servicios

## 📋 Requisitos

- **Docker** y **Docker Compose** instalados
- **Puertos libres:** 3306 (MySQL), 8001 (PHP), 8080 (Adminer)

---

## 🚀 Paso 1: Levantar MySQL + Adminer

```bash
cd /home/melquisedec/docker/mysql
docker compose up -d
```

Esto levanta:
| Contenedor | Puerto | Imagen |
|---|---|---|
| `mysql_himnario_v1` | `3306` | `mysql:8.0` |
| `adminer_himnario` | `8080` | `adminer:latest` |

### Credenciales MySQL
- **Host:** `mysql` (dentro de Docker) o `127.0.0.1` (desde el host)
- **Puerto:** `3306`
- **DB:** `himnario_db`
- **Usuario:** `himnario_user`
- **Contraseña:** `userpassword123`

### Inicialización automática
Al primer inicio, Docker ejecuta automáticamente los scripts en `./init/`:
1. `01-schema.sql` → Crea las 13 tablas
2. `02-seed-admin.sql` → Crea el usuario admin

---

## 🚀 Paso 2: Levantar PHP (Servidor Web)

```bash
# Eliminar contenedor anterior si existe (ignorar error si no existe)
docker rm -f php_himnario15 2>/dev/null

# Crear y ejecutar contenedor PHP
docker run -d --name php_himnario15 \
  -v /home/melquisedec/Escritorio/Projects/Personales/himnario_1.5:/var/www/html \
  -p 8001:80 php:8.2-cli \
  php -S 0.0.0.0:80 -t /var/www/html

# Esperar a que inicie
sleep 3

# Instalar extensión MySQLi
docker exec php_himnario15 docker-php-ext-install mysqli > /dev/null 2>&1

# Conectar a la red de MySQL
docker network connect mysql_default php_himnario15

# Reiniciar para que tome la conexión de red
docker restart php_himnario15

echo "✅ Himnario corriendo en http://localhost:8001"
```

---

## 🚀 Comando Único (todo en uno)

```bash
docker compose -f /home/melquisedec/docker/mysql/docker-compose.yml up -d && \
docker rm -f php_himnario15 2>/dev/null; \
docker run -d --name php_himnario15 \
  -v /home/melquisedec/Escritorio/Projects/Personales/himnario_1.5:/var/www/html \
  -p 8001:80 php:8.2-cli php -S 0.0.0.0:80 -t /var/www/html && \
sleep 3 && \
docker exec php_himnario15 docker-php-ext-install mysqli > /dev/null 2>&1 && \
docker network connect mysql_default php_himnario15 && \
docker restart php_himnario15 && \
echo "✅ Himnario corriendo en http://localhost:8001"
```

---

## 🔗 URLs de Acceso

| Servicio | URL |
|---|---|
| 🌐 **App Web** | [http://localhost:8001](http://localhost:8001) |
| 🗄️ **Adminer (DB)** | [http://localhost:8080](http://localhost:8080) |
| 🔐 **Login Admin** | `admin` / `admin123` |

---

## 🛑 Apagar servicios

```bash
# Apagar MySQL + Adminer
docker compose -f /home/melquisedec/docker/mysql/docker-compose.yml down

# Apagar PHP
docker stop php_himnario15
docker rm php_himnario15
```

---

## 📁 Estructura del proyecto

```
📁 himnario_1.5/
├── 📄 index.php              → Página principal (búsqueda)
├── 📄 api_search.php         → Endpoint AJAX (búsqueda sin recarga)
├── 📄 login.php              → Login de administrador
├── 📄 presentacion.php       → Visor para proyector
├── 📄 GUIA_LEVANTAR_SERVICIOS.md  → Esta guía
├── 📄 phpService.txt         → Comando único de inicio
├── 📄 himnario_db.sql        → Dump de la base de datos
├── 📄 README.md              → Documentación completa
├── 📁 admin/
│   ├── 📄 index.php          → Panel de control
│   ├── 📄 crear.php          → Crear himno
│   ├── 📄 editar.php         → Editar himno
│   ├── 📄 eliminar.php       → Eliminar himno
│   └── 📄 logout.php         → Cerrar sesión
├── 📁 includes/
│   ├── 📄 db.php             → Conexión a MySQL
│   └── 📄 funciones.php      → Funciones auxiliares
├── 📁 css/
│   └── 📄 style.css          → Sistema de diseño (6 temas)
└── 📁 js/
    └── 📄 app.js             → JS modular (7 módulos)
```

---

## 📦 Para subir a un hosting

1. **Subir todo** el contenido de `himnario_1.5/` al servidor (excepto archivos Docker)
2. **Crear una base de datos** MySQL llamada `himnario_db`
3. **Importar** `himnario_db.sql` en esa base de datos
4. **Configurar** `includes/db.php` con los datos de conexión del hosting
5. La aplicación debería funcionar si el hosting soporta PHP 8.0+ y MySQL 5.7+

---

## 🐳 Notas para Docker

- El archivo `docker-compose.yml` está en: `/home/melquisedec/docker/mysql/docker-compose.yml`
- Los scripts de inicialización están en: `/home/melquisedec/docker/mysql/init/`
- El contenedor PHP usa `php:8.2-cli` con el servidor interno de PHP
- La red `mysql_default` se crea automáticamente al hacer `up` con Docker Compose
- El PHP se conecta a MySQL usando el hostname `mysql` (resuelto por Docker DNS)

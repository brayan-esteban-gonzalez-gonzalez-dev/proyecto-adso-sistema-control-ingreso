# 📋 SCIA — Sistema de Control de Ingreso de Aprendices (RFID)

Sistema web en PHP (MVC) con MySQL/PDO para controlar el ingreso y salida de aprendices mediante llaveros RFID, generar reportes de asistencia y gestionar excusas médicas.

## 🚀 Tecnologías

- **Backend:** PHP 8.2+ (MVC nativo)
- **Base de datos:** MySQL / MariaDB con PDO
- **Frontend:** HTML5, CSS3 (Dark Theme Premium), JavaScript Vanilla
- **Reportes:** Dompdf (PDF), PhpSpreadsheet (Excel)
- **Servidor:** Apache (XAMPP/WAMP/Laragon)

## ⚡ Instalación Rápida

### 1. Clonar el proyecto
```bash
git clone <repo-url> SistemaControlIngresoAprendices-mvc
```
Copiar a `C:\xampp\htdocs\proyects\segundoCrud\SistemaControlIngresoAprendices-mvc\`

### 2. Crear la base de datos
1. Abrir **phpMyAdmin** → `http://localhost/phpmyadmin`
2. Ir a la pestaña **Importar**
3. Seleccionar el archivo `database/schema.sql`
4. Ejecutar

### 3. Instalar dependencias (Composer)
```bash
cd SistemaControlIngresoAprendices-mvc
composer install
```

> Si no tiene Composer instalado: https://getcomposer.org/download/

### 4. Configurar credenciales
Editar `config/Database.php` si las credenciales de MySQL son diferentes a las de XAMPP por defecto (`root` / `""`).

### 5. Iniciar Apache y MySQL
Abrir **XAMPP Control Panel** → Iniciar **Apache** y **MySQL**.

### 6. Acceder al sistema
```
http://localhost/proyects/segundoCrud/SistemaControlIngresoAprendices-mvc/public/
```

## 🔐 Credenciales por Defecto

| Rol | Documento | Contraseña |
|-----|-----------|------------|
| Administrador | `admin` | `password` |

> ⚠️ **Cambiar la contraseña del administrador** en producción.

## 📁 Estructura del Proyecto

```
├── config/Database.php              # Conexión PDO singleton
├── database/schema.sql              # Script de la BD
├── models/                          # 7 modelos de datos
├── controllers/                     # 9 controladores
├── views/                           # Vistas organizadas por módulo
│   ├── layouts/header.php|footer.php
│   ├── auth/login.php
│   ├── dashboard/admin.php|aprendiz.php
│   ├── usuarios/index.php|form.php
│   ├── fichas/index.php|form.php
│   ├── horarios/index.php|form.php
│   ├── aprendices/index.php|form.php
│   ├── asistencia/rfid.php|historial.php
│   ├── excusas/index.php|form.php|revisar.php
│   └── reportes/index.php
├── public/
│   ├── index.php                    # Front controller
│   └── assets/css|js|uploads/
├── helpers/Auth.php|Router.php|Validator.php
├── api/rfid.php                     # API REST para lector RFID
├── cron/mantenimiento.php           # Limpieza diaria
└── composer.json
```

## ✅ Funcionalidades

- **Login seguro** con contraseñas bcrypt y tokens CSRF
- **Dashboard** con estadísticas según rol (admin/aprendiz)
- **CRUD completo** de usuarios, fichas, horarios y aprendices
- **Marcación RFID** en tiempo real con feedback visual
- **Diagnóstico automático**: A Tiempo / Retardo / Salida Temprana / Inasistencia
- **Excusas médicas** con carga de archivo y flujo de aprobación/rechazo
- **Reportes** filtrables con exportación a PDF y Excel
- **Mantenimiento automático** de registros al cierre de jornada
- **API REST** para integración con lectores RFID físicos
- **Diseño responsive** con tema oscuro premium

## 🔧 Mantenimiento Automático

Configurar una tarea programada para ejecutar diariamente a las 23:59:

**Windows (Task Scheduler):**
```
Programa: C:\xampp\php\php.exe
Argumentos: C:\xampp\htdocs\...\cron\mantenimiento.php
```

**Linux (Crontab):**
```
59 23 * * 1-6 /usr/bin/php /ruta/cron/mantenimiento.php
```

## 📡 API RFID

Endpoint para lectores RFID físicos:

```bash
POST /api/rfid.php
Content-Type: application/json

{"rfid_uid": "AB12CD34"}
```

**Respuesta:**
```json
{
  "success": true,
  "accion": "entrada",
  "estado": "A_Tiempo",
  "aprendiz": "Juan Pérez",
  "hora": "07:05:23"
}
```

## 👤 Autor

Proyecto académico — SENA

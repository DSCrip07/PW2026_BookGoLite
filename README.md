# Book&Go Lite – Navaja & Estilo (Barbería)

**Universidad Tecnológica de Panamá · FISC · Desarrollo Web**
**Proyecto Parcial 2 – Profesor: Giovani Sánchez**

Sistema web para reservar citas en una barbería, con reseñas de los clientes.
Desarrollado con PHP, MySQL, HTML5, CSS3 y JavaScript.

## Grupo

**Grupo:** [completar número]

| Integrante | Usuario de GitHub | Módulo |
|---|---|---|
| Josua Martinez | [DSCrip07](https://github.com/DSCrip07) | Autenticación y sesiones |
| Cristhofer Villarreal | [cristhofer-11](https://github.com/cristhofer-11) | Catálogo y administración |
| Brayan Mela | [brayanmela17-hub](https://github.com/brayanmela17-hub) | Horarios y reservas |
| Ian Ramos | [ianramos21-star](https://github.com/ianramos21-star) | Reseñas, interfaz y pruebas |

## Negocio elegido
Barbería **Navaja & Estilo**: cortes, barba y tratamientos capilares.

## Funcionalidades
- **Cliente:** registro, inicio de sesión, catálogo con búsqueda y filtro por categoría, reservas, cancelaciones y reseñas.
- **Administrador:** gestión de categorías, servicios, horarios y reservas (cambio de estados).
- **Seguridad:** contraseñas con hash, consultas preparadas (PDO), protección XSS y CSRF, bloqueo tras intentos fallidos, control de acceso por rol y cabeceras HTTP.

## Estado
Hecho: base de datos de 7 tablas, registro/login/logout, navegación por rol, CRUD de categorías, servicios y horarios, catálogo, reservas con control de cupos, cancelación, estados y reseñas.
Pendiente: pruebas de seguridad documentadas (`security/SECURITY.md`) y PDF final.

## Cómo ejecutar el sistema (XAMPP, Windows)
1. Iniciar **Apache** y **MySQL** desde el panel de XAMPP y copiar el proyecto en `C:\xampp\htdocs\PW2026_BookGoLite`.
2. Importar la base de datos (crea `bookgo_barberia` con las 7 tablas y datos de prueba):
   `C:\xampp\mysql\bin\mysql.exe -u root < database\schema.sql`
3. Crear el usuario ADMIN (la contraseña se pide por teclado; debe tener mayúscula, minúscula, número y carácter especial):
   `C:\xampp\php\php.exe database\create_admin.php`
4. Abrir **http://localhost/PW2026_BookGoLite/public/**

Usuarios de prueba (clientes, contraseña `Cliente123!`): `carlos@ejemplo.com`, `luis@ejemplo.com`, `andres@ejemplo.com`.
Más detalles y alternativas en [INSTALACION.md](INSTALACION.md). Pruebas y evidencias de seguridad en [security/SECURITY.md](security/SECURITY.md).

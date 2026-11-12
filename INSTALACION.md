# Instalación y ejecución (XAMPP en Windows)

1. Clonar el proyecto en `C:\xampp\htdocs` e iniciar **Apache** y **MySQL** desde XAMPP.
2. Importar la base de datos (crea `bookgo_barberia` con datos de prueba):
   ```
   C:\xampp\mysql\bin\mysql.exe -u root < database\schema.sql
   ```
3. Crear el usuario ADMIN (la contraseña se pide por teclado; no está en el repositorio):
   ```
   C:\xampp\php\php.exe database\create_admin.php
   ```
4. Abrir http://localhost/PW2026_BookGoLite/public/

Alternativa sin Apache: dentro de `public/` ejecutar `php -S 127.0.0.1:8000` y abrir http://127.0.0.1:8000

Si MySQL tiene contraseña, crear `config/config.local.php` (ignorado por git):
```php
<?php return ['db_pass' => 'su_clave'];
```

## Usuarios de prueba
Clientes (contraseña `Cliente123!`): `carlos@ejemplo.com`, `luis@ejemplo.com`, `andres@ejemplo.com`.

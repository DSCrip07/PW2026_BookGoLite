<?php
declare(strict_types=1);
/**
 * Política de contraseñas (sin efectos secundarios: se puede usar desde web y consola).
 * Devuelve la lista de requisitos que NO cumple la contraseña (vacía = válida).
 */
function errores_password(string $clave): array
{
    $e = [];
    if (mb_strlen($clave) < 8)                    $e[] = 'al menos 8 caracteres';
    if (!preg_match('/\p{Lu}/u', $clave))         $e[] = 'una letra mayúscula';
    if (!preg_match('/\p{Ll}/u', $clave))         $e[] = 'una letra minúscula';
    if (!preg_match('/\d/', $clave))              $e[] = 'un número';
    if (!preg_match('/[^\p{L}\d\s]/u', $clave))   $e[] = 'un carácter especial (por ejemplo ! @ # $ % & *)';
    return $e;
}

function mensaje_password(array $faltan): string
{
    return 'La contraseña debe tener: ' . implode(', ', $faltan) . '.';
}

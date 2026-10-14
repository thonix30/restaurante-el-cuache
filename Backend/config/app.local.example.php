<?php
// Opcional. Si no existe app.local.php, la API lo crea sola la primera vez
// con un jwt_secret aleatorio. Úsalo para fijar valores propios del servidor.
// app.local.php NO se sube a GitHub ni lo pisa el despliegue.
return [
    // Firma de las sesiones. Cambiarla solo obliga a todos a iniciar sesión de nuevo.
    // 'jwt_secret' => 'una-cadena-larga-y-aleatoria',

    // Solo si el sistema y el sitio viven en dominios distintos:
    // 'cors_origin' => 'https://tudominio.com',
];

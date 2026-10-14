# Despliegue en Hostinger

**Dominio:** https://restaurantelcuache.thonixdev.es
**Carpeta en el servidor:** `/home/u622087758/domains/thonixdev.es/public_html/restaurantelcuache`

| Dirección | Qué es |
|---|---|
| `restaurantelcuache.thonixdev.es/` | Sitio público del restaurante |
| `restaurantelcuache.thonixdev.es/sistema/` | Sistema administrador (login, salón, cocina, caja…) |
| `restaurantelcuache.thonixdev.es/sistema/#/kiosco` | Kiosco de asistencia |
| `restaurantelcuache.thonixdev.es/Backend/api/v1/` | API |

El workflow `.github/workflows/deploy.yml` hace **CI/CD** (GitHub → pestaña **Actions**):

| Etapa | Cuándo corre | Qué hace |
|---|---|---|
| **CI · Verificar y compilar** | En cada push a cualquier rama y en cada Pull Request | Revisa la sintaxis de todo el PHP, compila el sistema y arma la carpeta de despliegue. Si falla, el PR muestra ❌. |
| **CD · Desplegar en Hostinger** | Solo cuando cambia `main` (o a mano) y si CI pasó | Sincroniza por **SSH + rsync** (solo sube lo que cambió) y comprueba que el sitio, el sistema y la API respondan. |

---

## Configuración inicial (una sola vez)

### 1. Subdominio y PHP
1. hPanel → **Dominios → Subdominios**: crea `restaurantelcuache` apuntando a la carpeta `public_html/restaurantelcuache`.
2. hPanel → **Avanzado → Configuración de PHP**: versión **8.1 o superior** (recomendado 8.2).
3. hPanel → **Seguridad → SSL**: activa el certificado para el subdominio (para instalar la app en el celular hace falta HTTPS).

### 2. Base de datos
1. hPanel → **Bases de datos → Bases de datos MySQL**: crea una base y un usuario (anota nombre, usuario y contraseña; Hostinger les antepone `u622087758_`).
2. Entra a **phpMyAdmin** con esa base → pestaña **Importar** → sube `Database/hostinger.sql`.
   Trae el esquema y los datos iniciales (carta, mesas, personal y usuarios de prueba).

### 3. Credenciales de la base en el servidor
En el **Administrador de archivos**, dentro de `restaurantelcuache/Backend/config/`, crea `database.local.php` (puedes copiar `database.local.example.php`):

```php
<?php
return [
    'host'     => 'localhost',
    'dbname'   => 'u622087758_NOMBRE_DE_LA_BASE',
    'username' => 'u622087758_USUARIO',
    'password' => 'CONTRASEÑA',
];
```

Este archivo **no está en GitHub** y el despliegue **nunca lo pisa**. `app.local.php` (clave de las sesiones) se crea solo la primera vez que se usa la API.

> La carpeta `Backend/config/` aparece después del primer despliegue (paso 4). Si quieres, crea el archivo justo después.

### 4. Acceso SSH y secreto de GitHub
**Servidor SSH:** `u622087758@145.79.4.70`, puerto `65002` (hPanel → Avanzado → Acceso SSH).

1. La llave de despliegue está en tu PC: `C:\Users\ANTHONY\.ssh\elcuache_deploy` (privada) y `elcuache_deploy.pub` (pública).
2. La **pública** se registra en hPanel → Acceso SSH → **Claves SSH** (nombre `deploy-elcuache`).
3. La **privada** va en GitHub → repositorio → **Settings → Secrets and variables → Actions → New repository secret**:
   - **Nombre:** `SSH_PRIVATE_KEY`
   - **Valor:** el contenido completo del archivo, incluidas las líneas `-----BEGIN…` y `-----END…`.

   Para copiarla al portapapeles, en PowerShell: `Get-Content $env:USERPROFILE\.ssh\elcuache_deploy -Raw | Set-Clipboard`

Para entrar tú al servidor desde la terminal:
```
ssh -i ~/.ssh/elcuache_deploy -p 65002 u622087758@145.79.4.70
```

### 5. Primer despliegue
GitHub → **Actions → "CI/CD · El Cuache → Hostinger" → Run workflow** (rama `main`), o haz un push a `main`.

### 6. Después de instalar
1. Entra a `/sistema/#/login` con `admin` / `cuache2026` y **cambia la contraseña** (Mi perfil).
2. Cambia o desactiva a los usuarios de prueba en **Personal**, y asígnales PIN nuevos.
3. Revisa **Ajustes** (nombre, horario, propina) y genera e imprime los **Códigos QR**: ya usan la dirección del subdominio.

---

## Qué se sube y qué no
- **Se sube:** Sitio, sistema compilado, API.
- **Nunca se sube ni se borra en el servidor:**
  - `Backend/config/*.local.php` (credenciales);
  - `Backend/uploads/f_*` (fotos de platillos y del personal);
  - `Backend/storage/documentos` y `Backend/storage/asistencia` (archivos privados).

## Si algo falla
- **La pestaña Actions marca error en "Preparar la llave SSH" o "Probar la conexión":** revisa el secreto `SSH_PRIVATE_KEY` y que la llave pública siga registrada en hPanel.
- **El sistema dice "No se pudo conectar con la base de datos":** revisa `database.local.php`.
- **Ves una versión vieja:** el sistema es una app instalable (PWA): al publicar, aparece el aviso **"Hay una versión nueva · Actualizar"**. Si no aparece, recarga con **Ctrl + F5**.
- **El despliegue avisa "La CDN de Hostinger sirve un sw.js viejo":** hPanel → Sitios web → thonixdev.es → **Rendimiento → CDN → Purgar toda la caché**. Solo hace falta si la CDN guardó el archivo antes de tiempo; `Frontend/public/.htaccess` ya le indica que no lo guarde.

## App instalable (PWA)
- **Android / computadora (Chrome, Edge):** botón **Instalar app** en el inicio de sesión y en el menú (o el ícono de instalar de la barra de direcciones).
- **iPhone / iPad:** en Safari, **Compartir → Agregar a inicio** (el botón *Instalar app* muestra los pasos).
- Sin internet abre igual y muestra la carta guardada; los cambios (pedidos, cobros) necesitan conexión y se avisa con "Sin conexión".

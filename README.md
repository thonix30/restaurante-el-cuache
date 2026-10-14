# El Cuache · Café y Restaurante

Sistema de gestión (PWA instalable) para celular, tablet y escritorio: menú digital por QR, salón, toma de pedidos, cocina (KDS), caja, inventario, estadísticas, auditoría y gestión completa del personal (fichas, turnos, kiosco de asistencia, solicitudes y reportes).

Es la reimplementación de **SistemaRestauranteEl-Jardin** sobre la arquitectura de **template-main**, con la lógica, la estructura y la UI/UX rediseñadas.

---

## Puesta en marcha (XAMPP)

1. Arranca **Apache** y **MySQL** desde el panel de XAMPP.
2. Crea la base de datos:
   ```bash
   C:\xampp\mysql\bin\mysql.exe -u root < Database/schema.sql
   C:\xampp\mysql\bin\mysql.exe -u root < Database/seed.sql
   # Opcional: 21 días de ventas, asistencia y un servicio en curso
   C:\xampp\php\php.exe Database/demo.php
   ```
3. Abre **http://localhost/Restaurante%20el%20cuache/** (redirige a `Frontend/dist/`).

> El frontend ya está compilado en `Frontend/dist`. Para modificarlo:
> ```bash
> cd Frontend
> npm install
> npm run dev     # desarrollo en http://localhost:5173 (usa la API de XAMPP)
> npm run build   # vuelve a generar Frontend/dist
> ```

### Usuarios de prueba

| Usuario | Contraseña  | Rol          | PIN kiosco |
|---------|-------------|--------------|-----------|
| admin   | cuache2026  | Administrador | 1234 |
| carla   | mesero123   | Mesera       | 2345 |
| diego   | equipo123   | Mesero       | 5678 |
| luis    | cocina123   | Cocinero     | 3456 |
| —       | —           | Sofía (Bartender), María (Cajera), Jorge (Ayudante): solo kiosco | 4567 · 6789 · 7890 |

**Cambia las contraseñas y los secretos antes de producción** (ver *Producción*).

### Rutas principales

| Pantalla | Ruta |
|---|---|
| Portada pública (parallax) | `#/` |
| Menú del cliente (QR de la mesa) | `#/m/<token>` (cada mesa tiene su QR en **Códigos QR**) |
| Carta sin pedir | `#/carta` |
| Kiosco de asistencia (tablet) | `#/kiosco` |
| Login | `#/login` |
| Admin | `#/panel` · Mesero `#/salon` · Cocina `#/cocina` |

---

## Arquitectura (template-main)

```
Backend/                      API PHP sin framework
  api/v1/index.php            Entrada: CORS + registro de controladores + Router
  config/                     database.php, app.php (secretos JWT y PIN)
  src/Attributes/             #[Route], #[Authorize], #[HasPrivilege], #[PublicRoute]
  src/Core/                   Router (atributos), Controller, HttpException
  src/Controllers/            Capa HTTP delgada (valida rol y delega)
  src/Services/               Reglas de negocio (pedidos, cobros, caja, turnos, asistencia…)
  src/Repositories/           SQL con sentencias preparadas
  src/DTOs/                   Validación de entrada con errores por campo
  src/Entities/               Formato de salida tipado (sin secretos)
  src/Utils/                  Database, JwtUtils, Auth, Audit, Storage, Time, Url…
  uploads/                    Fotos públicas (platillos, perfiles)
  storage/                    PRIVADO: documentos del personal, fotos de asistencia
Frontend/                     Vite + Vue 3 + Pinia + Tailwind + GSAP + PWA
  src/services/               api.js (axios + JWT) y servicios por dominio
  src/stores/                 auth, ui (tema/avisos), realtime (sondeo en vivo), cart
  src/components/             layout, ui, charts, salon, catalogo, personal
  src/views/                  23 pantallas (public, auth, admin, salon, cocina, personal…)
Database/                     schema.sql, seed.sql, demo.php
```

Flujo de una petición: `Router` → lee los atributos `#[Route]`/`#[Authorize]` → `Controller` → `DTO::fromRequest()` (valida) → `Service` (transacción, reglas, auditoría) → `Repository` (SQL) → `Entity` (salida).

**Tiempo real:** sondeo liviano (`GET /sync` cada 4 s para el personal y `/public/mesa/{token}/estado` cada 5 s para el cliente). Funciona en cualquier hosting PHP compartido, sin WebSockets.

---

## Qué cambió respecto a El Jardín

**Lógica**
- **Cuentas y comandas separadas.** La cuenta de la mesa (lo que se cobra) ya no se mezcla con cada envío a cocina. Antes, al agregar platillos a un pedido ya servido, todo volvía a "Pendiente" en cocina.
- **Estado de mesa derivado.** Libre / ocupada / por cobrar / llamando / pidió la cuenta se calcula en vivo y ya no puede quedar "Ocupada" por error.
- **QR con token secreto por mesa.** Antes bastaba cambiar `?mesa=Mesa 7` en la URL para pedir a otra mesa. El QR se puede regenerar.
- **Llamar al mesero y pedir la cuenta** (no existían). Incluyen método de pago, cancelación por el cliente, bandeja con tiempo de espera, sonido y vibración.
- **Agotado automático por stock.** Al reponer stock (compra o ajuste) el platillo vuelve solo. Reactivar un agotado por stock exige indicar las unidades.
- **Validación de stock con filas bloqueadas.** Dos pedidos simultáneos no venden de más. Si algo se agota justo al enviar, el carrito lo marca y bloquea el envío.
- **Cancelación parcial de platillos con motivo** y devolución de stock. No se puede cancelar lo ya cobrado sin anular antes el pago.
- **Caja:** se puede cerrar aunque no cuadre, pero la diferencia exige una explicación (antes quedaba el turno abierto indefinidamente). No cierra con cuentas abiertas, y no se anulan pagos de turnos ya cerrados.
- **Login:** bloqueo progresivo (60 s, luego 120 s, 240 s…) con cuenta regresiva en pantalla. JWT validado contra la base de datos, así que quitar el acceso surte efecto al instante.
- **Borrado lógico de platillos.** El historial conserva nombre, costo y categoría de cada venta.

**Nuevo: módulo de personal completo**
- **Ficha del empleado:**
  - foto, datos personales y contacto de emergencia;
  - contrato y pago (mensual o por hora);
  - tallas y notas médicas;
  - documentos privados con vencimiento.
- **Acceso al sistema:** usuario, rol y contraseña, más un PIN de 4 dígitos guardado como HMAC. Siempre debe quedar al menos un administrador con acceso.
- **Planificador semanal:**
  - turnos con arrastrar y soltar (o modo pincel en tablet);
  - conflictos: cruces, horas semanales y descanso mínimo;
  - cobertura por hora;
  - copiar la semana anterior, plantillas rotativas y publicar el horario.
- **Kiosco:** PIN, cuatro acciones y foto opcional como comprobante.
- **Asistencia:**
  - estados: a tiempo, tarde +N min, falta, salida anticipada, horas extra, sin salida;
  - justificaciones con adjunto;
  - correcciones auditadas con los valores de antes y después.
- **Solicitudes:** vacaciones (con saldo), permisos e intercambio de turno. Al aprobarse, se aplican al horario.
- **Reporte de horas:**
  - horas normales y extra, faltas y puntualidad;
  - propinas repartidas según quién estaba en turno al cobrar;
  - pago estimado;
  - exportación a Excel (CSV) y PDF.
- **Alertas:** documentos por vencer, cumpleaños, quién no marcó salida, falta de personal y stock bajo.

**Diseño**
- **Identidad "noche de café":** espresso, crema del logo y dorado, con ramas de café en trazo fino.
- **Tipografía:** Fraunces, Manrope y JetBrains Mono para cifras.
- **Modo claro** opcional.
- **Animaciones GSAP:**
  - parallax en la portada y el menú, con el título revelado letra a letra;
  - ramas que se dibujan en el login;
  - KPIs que cuentan;
  - platillo que "vuela" al carrito;
  - check animado al marcar asistencia.
  - Respetan `prefers-reduced-motion`.
- **Gráficas propias en SVG:** paleta validada para daltonismo y contraste, con tooltips y vista en tabla.
- **Navegación:** inferior en celular y barra lateral colapsable en escritorio.

---

## Producción

**Hostinger:** ver [DESPLIEGUE.md](DESPLIEGUE.md). Despliegue automático a https://restaurantelcuache.thonixdev.es en cada push a `main`.


- Crea `Backend/config/database.local.php` y `Backend/config/app.local.php` (devuelven un array con las claves a reemplazar).
- Cambia `jwt_secret`. **No cambies `pin_secret`** después de asignar los PIN: dejarían de coincidir.
- Si la carpeta del sistema cambia, la app calcula sola la URL de la API (`../../Backend/api/v1`). Para otro dominio, define `VITE_API_URL` al compilar.
- Asegura que `Backend/uploads` y `Backend/storage` tengan permiso de escritura. `storage` nunca se sirve directo (`.htaccess`).

# Sitio público · El Cuache (San Antonio Huista)

Página de presentación del restaurante, **independiente del sistema administrador**. Es HTML, CSS y JS estático, sin compilación.

- Abrir: `http://localhost/Restaurante%20el%20cuache/Sitio/`
- Animaciones: GSAP 3.15 local (`js/vendor`) con ScrollTrigger, ScrollSmoother (parallax y scroll suave en escritorio) y SplitText.
- Tipografías: **Caprasimo** (rótulo pintado a mano) + **Familjen Grotesk**.
- Colores: tintes de los tejidos de Huehuetenango.

## Ajustes rápidos (`js/main.js` → `CONFIG`)
- `whatsapp`: número con código de país (ej. `50255555555`). Vacío = el botón no se muestra.
- `horario`: texto del horario.
- `api` / `app`: rutas a la API pública y a la app (solo lectura de la carta).

La sección "La carta de hoy" lee los platillos disponibles del sistema. Si la API no responde, muestra una lista de respaldo sin precios.

## Fotografías
Todas son de **Wikimedia Commons** con licencias libres (dominio público, CC0, CC BY o CC BY-SA). El pie de página muestra el autor y la licencia de cada una (`img/creditos.json`); **no los quites**, porque las licencias exigen atribución. Algunas son de la región (San Antonio Huista, Jacaltenango, Colotenango, Cuchumatanes) y otras de Guatemala en general; cada foto lleva su pie con el lugar real.

Para usar fotos propias del restaurante, reemplaza el archivo en `img/` con el mismo nombre. Si el reemplazo es tuyo, quita su crédito del JSON.

# Demo independiente en GitHub Pages

El workflow **Publish frontend to GitHub Pages** publica una edición autónoma del juego, sin Laravel ni PostgreSQL. Viaje, saqueo, inventario, consumo, descanso y reinicio se ejecutan en el navegador. El progreso se conserva en localStorage, solamente en ese navegador y dominio; borrar sus datos elimina la partida. No se sincroniza entre dispositivos y no ofrece autoridad de servidor. Web Locks serializa las acciones entre pestañas de los navegadores compatibles.

Todos los lugares son **ficticios**, exportados del catálogo demo; el mapa base utiliza OpenFreeMap con atribución OSM. No importa establecimientos reales. La importación OSM y el backend PostGIS siguen disponibles en la aplicación Laravel, sin cambios de arquitectura.

## Publicación

1. Sube el código a `Noraus/zomb-surv`.
2. En Settings → Pages elige **Deploy from a branch**, rama **gh-pages**, carpeta **/ (root)** si se publica el build directamente; o selecciona **GitHub Actions** para usar el workflow incluido.
3. Para Actions ejecuta manualmente **Publish frontend to GitHub Pages** en la pestaña Actions.

El workflow no necesita secretos ni una API externa. Genera el sitio bajo la ruta del nombre del repositorio. No se activa automáticamente al subir cambios.

## Comprobar el build

```bash
VITE_DEMO_MODE=true npm run build:pages
npx vite preview --config vite.pages.config.ts --host 0.0.0.0
# En otra terminal:
npx playwright test --config playwright.pages.config.ts
```

El sitio está en la ruta `/zomb-surv/`. Para otra ruta establece `PAGES_BASE_PATH=/otra/` al compilar. Las pruebas inician su propio servidor preview; detén el preview manual antes de ejecutarlas.

`resources/js/demo.ts` implementa la simulación local; `demo-data.json` contiene solo datos ficticios; `api.ts` selecciona el adaptador con `VITE_DEMO_MODE`. Sin esa variable, la aplicación conserva su API Laravel habitual. Para exportar de nuevo las definiciones demo: `docker compose exec -T app php scripts/export-demo.php > resources/js/demo-data.json`.

También se admite un frontend estático conectado a Laravel con `VITE_API_BASE_URL=https://TU_BACKEND/api/v1` y sin `VITE_DEMO_MODE`. En ese caso configura `FRONTEND_ORIGIN=https://noraus.github.io` en el backend. Esa variante requiere alojamiento PHP/PostGIS aparte y no es la demo elegida para Pages.

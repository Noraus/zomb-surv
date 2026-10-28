# Última señal · Donostia

Prototipo survival para navegador: mapa geográfico real, un superviviente y recursos finitos persistentes. Laravel 13, PHP 8.4, React 19 + TypeScript, Vite 8, Tailwind 4, MapLibre GL JS y PostgreSQL 17/PostGIS 3.5. Una aplicación y una API; no utiliza Inertia ni requiere cuentas, GPS o claves privadas.

Los lugares demo son **ficticios**. Los lugares importados proceden de OpenStreetMap. **Todo el botín, los infectados y los niveles de riesgo son simulaciones del juego y no información real sobre establecimientos.**

## Arranque en Windows 11 + WSL2

Requisitos: WSL2 Ubuntu, Docker Desktop con integración WSL habilitada (o Docker Engine con Compose v2), Git y Node.js 22.12+ (recomendado 24). Guarda el proyecto en el sistema de archivos Linux, no en `/mnt/c`, para mejorar el rendimiento. Ejecuta desde Ubuntu, en la raíz del repositorio:

```bash
bash scripts/setup.sh
```

El script conserva `.env` si existe, construye PHP, inicia PostGIS, instala las versiones de los lockfiles, genera `APP_KEY` si falta, compila React y ejecuta migraciones y seeds idempotentes. Arranca el servidor de desarrollo en el puerto **8000**. No borra progreso ni volúmenes. `composer install --prefer-source` permite instalar desde Git cuando el entorno restringe las descargas por la API de GitHub; puede tardar en la primera ejecución.

Abre el navegador en el puerto 8000 de tu máquina. Para desarrollo con actualización automática del frontend, ejecuta además `npm run dev`; para usar el build estable, detén Vite y elimina `public/hot` si quedó tras un cierre inesperado.

Arranque posterior:

```bash
docker compose up -d app
```

Parada sin perder datos:

```bash
docker compose down
```

PostgreSQL guarda el estado en el volumen `pgdata`. **`docker compose down -v` elimina ese estado**, por lo que no se utiliza en el procedimiento normal. El servidor PHP integrado sirve para desarrollo; no es una configuración de producción.

## Comandos manuales

```bash
docker compose build app
docker compose up -d --wait db
cp .env.example .env # solo si aún no existe
# Composer usa la CA del sistema; scripts/setup.sh configura también Git en redes con proxy.
docker compose run --rm -u "$(id -u):$(id -g)" app composer install
npm ci
npm run build
docker compose run --rm -u "$(id -u):$(id -g)" app php artisan key:generate
docker compose run --rm -u "$(id -u):$(id -g)" app php artisan migrate --seed
docker compose up -d app
```

La imagen instala `pdo_pgsql`, `intl`, `zip`, `mbstring` y XML. El host no necesita PHP. La base se conecta como `db:5432` dentro de Compose; el puerto del host está restringido a loopback. Las credenciales incluidas son exclusivamente de desarrollo local.

## Probar el juego

1. Selecciona **Mundo demo** (18 lugares ficticios, todas las categorías).
2. Pulsa **Lugares**, elige **Supermercado · Sector 04** y consulta los recursos probables.
3. Viaja: el servidor calcula distancia, energía y minutos ficticios. No hay espera real.
4. Pulsa **Registrar localización**. El resultado muestra objetos, tiempo y consecuencias.
5. Abre **Inventario**, comprueba la carga y consume agua o comida.
6. Recarga la página: personaje, posición, reloj e inventario se conservan. El botín retirado no vuelve.
7. Descansa para recuperar energía. Hambre y sed aumentan solo por acciones; cerrar el navegador no avanza el reloj.

Los registros posteriores revelan las existencias restantes. El panel del lugar incluye historial y una inspección opcional de las etiquetas originales. El botón para centrar el mapa sigue al superviviente virtual, nunca a tu GPS. Los filtros, búsqueda y lista se aplican al área visible. Las zonas ambientales OSM se dibujan con su geometría cuando está disponible y **no se saquean como si fueran tiendas**.

Para reiniciar exclusivamente el mundo demo, utiliza **Desarrollo → Reiniciar solo demostración**, o:

```bash
docker compose exec app php artisan world:reset-demo --force
```

Los mundos demo y OSM tienen personajes, relojes y recursos separados. La importación no cambia automáticamente el conjunto seleccionado; cambia a **OpenStreetMap** después de importar.

## Importar datos reales

```bash
docker compose exec app php artisan world:import-osm --preset=donostia
# Bounding box: SUR,OESTE,NORTE,ESTE
docker compose exec app php artisan world:import-osm --bbox=43.305,-2.005,43.335,-1.965
```

También está disponible **Desarrollo → Importar Donostia** en entorno `local`. El preset ocupa aproximadamente 11 km². Límite máximo: 45 km² y 0,08° de latitud × 0,1° de longitud. No admite importaciones nacionales. La consulta selecciona exclusivamente categorías relevantes, identifica la aplicación, usa timeout, como máximo dos intentos, caché de una hora y un intervalo mínimo de un minuto. La importación es explícita; mover el mapa solo consulta la base local. No se utiliza Nominatim ni se descargan teselas en masa.

El comando devuelve código 1 si falla Overpass. El error se registra en `osm_imports` y `storage/logs/laravel.log`, y el demo sigue operativo. Reintenta después del intervalo o comprueba red/CA. La reimportación actualiza geografía, **nunca cantidades, visitas ni riesgo generado**. Los elementos eliminados de OSM no se borran automáticamente para proteger el estado del juego.

## Variables

Consulta `.env.example`:

| Variable | Uso |
| --- | --- |
| `APP_ENV=local` | Habilita herramientas de desarrollo. Deshabilitadas con `production`. |
| `APP_KEY` | Generada por Laravel; no se guarda en Git. |
| `DB_*` | PostgreSQL/PostGIS. `DB_HOST=db` con Compose. |
| `WORLD_SEED` | Semilla de mundos nuevos. Cambiarla no regenera mundos existentes. |
| `WORLD_ABUNDANCE` | Multiplica el botín inicial de localizaciones nuevas. |
| `WORLD_RISK` | Multiplicador de riesgo de las acciones. |
| `TRAVEL_MULTIPLIER=1.3` | Distancia en línea recta × factor, a 4 km/h. No representa una ruta real. |
| `PLAYER_CAPACITY=20` | Capacidad inicial, en kg, de personajes nuevos. |
| `MAP_STYLE` | Estilo MapLibre; por defecto OpenFreeMap Liberty. |
| `OVERPASS_URL` | Endpoint servidor, nunca configurable por una petición pública. |
| `SURVIVAL_CA_BUNDLE` | Opcional, ruta de la CA combinada del host para instalar detrás de un proxy. |

No hacen falta secretos externos. Los parámetros de generación se conservan con cada localización; modificar abundancia o tamaño no restaura su inventario.

## Acceso desde el móvil en la misma red

Utiliza la IP LAN del ordenador y el puerto 8000. Compose publica ese puerto en todas las interfaces, y PHP escucha en `0.0.0.0`. Permite TCP 8000 en el firewall de Windows **solo en la red privada**. En WSL2, el modo de red reflejada simplifica el acceso; en modo NAT puede ser necesario configurar un `netsh interface portproxy` del puerto 8000 de Windows hacia la IP actual de WSL y habilitar la regla de firewall. La IP WSL cambia al reiniciar. El móvil y el PC deben compartir red y el router no debe aislar los clientes Wi-Fi. No abras PostgreSQL al exterior.

Para pruebas móviles utiliza `npm run build` y el servidor PHP: así no hace falta publicar Vite. No publiques este prototipo en Internet: las acciones usan un único personaje sin autenticación. Las operaciones de desarrollo están protegidas por entorno, no por una cuenta de administrador.

## Pruebas

```bash
docker compose exec -T app php artisan test --compact
bash scripts/test-concurrency.sh
npm run build
npm run test:e2e
```

PHPUnit utiliza **survival_test**, PostgreSQL real con PostGIS, separado del juego. El contenedor crea esa base al inicializar un volumen nuevo. Para un volumen antiguo: `docker compose exec db createdb -U survival survival_test`. El test de concurrencia se niega a ejecutarse en otra base, reconstruye solamente `survival_test` y lanza dos procesos PHP independientes. No ejecutes simultáneamente PHPUnit y el test de concurrencia porque comparten esa base.

Playwright necesita Chromium. En este entorno se utiliza `/usr/bin/chromium`; para otro sistema instala Chromium de tu distribución o indica `PLAYWRIGHT_CHROMIUM_EXECUTABLE`. Se prueban escritorio, 375 × 812 y 390 × 844, viaje, saqueo, inventario y recarga. Estas pruebas **reinician el demo local**. Los fixtures OSM de PHPUnit no requieren conexión a Overpass. La carga de teselas reales requiere red; el flujo demo dispone de un fondo geográfico neutro si falla el proveedor, sin inventar calles.

## Arquitectura y ampliaciones

Consulta [docs/architecture.md](docs/architecture.md) y [docs/api.md](docs/api.md).

- `Classification`: registro central de categorías, etiquetas, prioridades y procedencia.
- `OpenStreetMapImportService`: consulta, normalización, validación espacial y actualizaciones.
- `LootGenerator`: catálogo y generación determinista por identidad, semilla y contexto.
- `WorldService`: mundos, fixtures, persistencia inicial, detalle e inventario.
- `Simulation`: viajes, tiempo, necesidades, transacciones, consumo y saqueos auditados.
- `resources/js/Map.tsx`: GeoJSON, clustering, zonas y posición virtual.
- `resources/js/app.tsx` y `resources/css/app.css`: HUD, navegación móvil y paneles.

Añade una categoría en `Classification::categories()` respetando el orden de prioridad y su grupo de botín. Añade su selector a la consulta Overpass y un test. Un recurso nuevo se declara en `LootGenerator::catalog()` con ID estable, grupo, peso, rareza y propiedades; ejecuta `db:seed` para actualizar definiciones. Modifica `LootGenerator::generate()` para variar tablas, probabilidades y contexto. Nunca uses `seed` para reponer existencias ya generadas. No se han implementado combate, crafteo, multijugador, economía, GPS ni ciclos en tiempo real.

## Datos, licencias y límites

© OpenStreetMap contributors, [ODbL](https://www.openstreetmap.org/copyright). El mapa predeterminado se sirve mediante [OpenFreeMap](https://openfreemap.org/quick_start/), con atribución visible. MapLibre GL JS usa BSD-3-Clause, Lucide ISC, Laravel y React MIT; consulta las licencias de las dependencias. Las etiquetas y geometrías importadas son datos derivados de OSM: conserva atribución y evalúa las obligaciones de compartir bajo ODbL si publicas una base derivada. El estado ficticio del juego está separado, pero esa separación no sustituye una revisión de licencia al distribuir datos.

OSM puede estar incompleto o desactualizado. Una zona industrial no prueba la actividad concreta de un edificio. Un edificio sin uso reconocido queda desconocido, nunca se transforma en una farmacia real. No se inventan coordenadas; se usan nodos, centros y geometrías recibidas. Las relaciones incompletas se conservan sin fabricar polígonos; véanse los límites geométricos en arquitectura. El demo no contiene nombres comerciales reales.

Para regiones mayores: preparar una importación offline por extractos `.osm.pbf` de Geofabrik (País Vasco), filtrado con osmium y carga por lotes conservando `osm:tipo:id`, reglas y estado simulado. No está implementado ni se descarga el extracto durante el arranque.

En el entorno cloud, habilita destinos de dependencias y GitHub; para el juego real también `tiles.openfreemap.org` y `overpass-api.de`. La configuración guardada requiere revisión y publicación del entorno. Las restricciones de red del entorno no se evitan desde la aplicación.

## GitHub Pages: demo autónoma

Disponible un build separado con `VITE_DEMO_MODE=true npm run build:pages`: guarda el progreso solo en el navegador y no necesita backend. Todos sus lugares son ficticios. Consulta [la guía de publicación](docs/github-pages.md). La aplicación Laravel habitual mantiene su persistencia PostGIS y autoridad de servidor.

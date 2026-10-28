# API REST v1

Base: `/api/v1`. JSON, errores de validación 422 (`message`, `errors`), no encontrado 404, límite de peticiones 429. Peticiones POST con `Content-Type: application/json`. `dataset` admite `demo` u `osm`; predeterminado `demo`. Es un prototipo local de un jugador, sin autenticación.

| Método y ruta | Resultado / parámetros |
| --- | --- |
| `GET /world` | Configuración, categorías, reloj, personaje, inventario y carga. |
| `GET /categories` | Registro central de categorías. |
| `GET /locations` | GeoJSON y estadísticas. `bbox=oeste,sur,este,norte`, `q`, `category`, `dataset`. Máximo 1500 elementos; indica `limited`. |
| `GET /zones` | Geometrías ambientales filtradas. |
| `GET /statistics` | Estadísticas del viewport y conjunto. |
| `GET /locations/{id}` | Geografía, recursos probables, stock solo tras explorar e historial reciente. |
| `GET /player`, `GET /inventory` | Estado autoritativo del mundo seleccionado. |
| `POST /actions/travel` | `{dataset, location_id}`; distancia Haversine y tiempo ficticio. |
| `POST /actions/scavenge` | `{dataset, location_id}`; posición persistida, energía y capacidad verificadas. |
| `POST /actions/rest` | `{dataset}`; avanza 60 minutos ficticios, recupera energía. |
| `POST /actions/consume` | `{dataset, resource_id}`; resta una unidad consumible. |
| `GET /dev/status` | Semilla, distribución por categoría/procedencia, recursos e importaciones. |
| `POST /dev/import` | `{bbox?: [sur,oeste,norte,este]}`; preset Donostia por defecto; 503 al fallar Overpass. |
| `POST /dev/reset` | Reinicia solamente el mundo demo. |

Acciones: `{event, world, player, inventory, weight, visited}`. Cada evento contiene el resultado calculado por servidor, minuto, tipo y localización. Viajes y saqueos no admiten cantidades ni costes suministrados por cliente. Coordenadas GeoJSON: **longitud, latitud**. Las rutas `dev` responden 404 fuera de `local` y `testing`. La importación por CLI permanece disponible para operadores.

Ejemplo de flujo (desde un cliente HTTP local): consultar `/locations?category=supermarket`, seleccionar un `properties.id`, enviar `/actions/travel` y después `/actions/scavenge` con ese ID. Recargar `/world` para comprobar persistencia. Nunca envíes coordenadas para autorizar un saqueo.

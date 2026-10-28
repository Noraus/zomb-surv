# Modelo e invariantes

`worlds` separa demo y OSM con semillas, relojes y personajes independientes. `world_locations` contiene geografía y procedencia, identidad estable única por mundo, campos de simulación y contexto de generación. Zonas y POI comparten tabla, diferenciados por `kind`. Geometrías y etiquetas originales se guardan en JSONB; `position` y `shape` usan SRID 4326 e índices GiST. Las búsquedas del viewport usan `ST_Intersects` y la influencia ambiental usa `ST_Covers`.

`resource_definitions` declara objetos. `location_resources` y `player_inventory` son inventarios distintos con constraints de no negatividad y claves únicas. `players` conserva posición, localización alcanzada, necesidades y carga máxima. `events` unifica viajes, saqueos, descanso y consumo, conservando el resultado auditable. `osm_imports` registra estado de solicitudes explícitas. La base se inicializa mediante migraciones Eloquent; no se requiere un servidor de colas.

## Geografía → clasificación → generación → simulación

1. `OpenStreetMapImportService` normaliza nodos, vías y relaciones. La identidad es `osm:node:ID`, `osm:way:ID` u `osm:relation:ID`; nunca el nombre. Un centro recibido o un punto derivado por PostGIS de geometría recibida proporciona la posición. Sin posición auténtica se omite el elemento.
2. `Classification` aplica la primera coincidencia del registro ordenado: actividades específicas antes de zonas genéricas. Conserva las etiquetas secundarias y la regla aplicada. `source=osm` y confianza 1 indican coincidencia explícita de etiquetas, **no verificación del establecimiento**. Demo: `source=simulated`, confianza 0. Edificios desconocidos no se importan como establecimientos identificados; no se generan inferencias en esta versión.
3. `LootGenerator` usa SHA-256 de semilla, ID estable, categoría y recurso. El grupo de categoría favorece su botín; hay recursos secundarios menos frecuentes. Tamaño geométrico acotado, abundancia y contexto industrial influyen en cantidades iniciales. El importador carga primero zonas para consultar contexto al crear POI. El contexto queda guardado, y los inventarios solo se crean con una localización nueva. Los cambios de categorías futuras no reponen existencias.
4. `Simulation` es la autoridad. Todas las acciones bloquean filas en orden **mundo → personaje → localización → recursos** dentro de transacciones. Se serializan acciones del mismo mundo (adecuado para un jugador) y la importación toma el mismo bloqueo. PostgreSQL impide cantidades negativas incluso ante errores. El test de concurrencia utiliza dos procesos y conexiones reales, sin el aislamiento artificial de una sola transacción PHPUnit.

## Tiempo y riesgo

Distancia Haversine, radio terrestre 6371 km. Tiempo = `ceil(km × TRAVEL_MULTIPLIER / 4 × 60)`, mínimo un minuto; energía = `ceil(minutos × 0.45)`. Es una estimación ficticia en línea recta; puede atravesar agua o edificios y no calcula accesibilidad ni rutas. No hay espera real.

Registrar consume 15 minutos y 8 puntos de energía; el riesgo puede causar 3 puntos de daño y 4 de energía extra. Aumenta con visitas previas y un multiplicador configurable. Una tirada reproducible por semilla, lugar y número de visitas evita recalcular resultados aleatorios al recargar. Se recogen hasta dos unidades de hasta tres tipos disponibles, siempre limitadas por peso. El fracaso por distancia, energía o capacidad revierte toda la acción. Un lugar agotado sigue consumiendo tiempo de registro y no regenera recursos.

Cada acción temporal aumenta hambre y sed; los consumibles reducen esas necesidades. Descansar recupera hasta 35 puntos de energía y avanza una hora. Con hambre/sed al máximo hay daño leve; en esta versión la salud mínima es 1, sin muerte permanente ni combate. Consultar endpoints, cerrar pestañas o reiniciar procesos no avanza el tiempo.

## Límites geométricos

Vías cerradas se representan como polígonos y vías abiertas como líneas. Relaciones multipolígono se reconstruyen solo con anillos auténticos cerrados; los fragmentos se unen únicamente si comparten extremos exactos. Los huecos se asignan a un exterior contenedor. Si la geometría es inválida o incompleta se conserva la posición/etiquetas sin inventar bordes. No se implementa una topología OSM general ni reparación heurística. Consultas a zonas con geometría nula siguen siendo posiciones cartografiadas, sin atribuir una superficie falsa.

## Ampliar

Añadir reglas/catálogos no requiere cambiar controladores. Nuevas mecánicas pueden usar el mismo historial, pero el bloqueo global de mundo tendrá que sustituirse por bloqueos de menor alcance antes de multijugador. Para escala regional: PBF → filtrado por categorías → normalización común → upsert por ID estable, con importaciones versionadas, política de retiradas y conservación de progreso. Un planificador futuro podrá implementar reabastecimiento explícito; no debe confundirse con una reimportación.

## Red y producción

El estilo Liberty y sus teselas necesitan OpenFreeMap. Las pruebas de lógica usan fixtures y HTTP simulado; la conectividad externa se comprueba por separado. Si falla el estilo, el mapa conserva capas de juego sobre un fondo neutro y la atribución permanece visible. Esto permite probar mecánicas, pero **no equivale a verificar un mapa de calles real**.

La API se diseñó para una máquina o LAN de confianza y un jugador. Antes de exposición pública hacen falta autenticación/autorización, CSRF o tokens según cliente, aislamiento de jugadores, TLS, servidor PHP de producción y credenciales propias de base. No se implementan esas ampliaciones fuera del alcance del prototipo.

# Indexación por lotes y cola reanudable

## Lotes de Joomla

Búsqueda inteligente ya indexa por lotes: cada petición procesa un grupo y el navegador solicita el siguiente. En **Componentes → Búsqueda inteligente → Opciones → Índice → Tamaño del lote**, empieza con **5 elementos** para PDF; reduce a **1** si son pesados. El valor predeterminado es 50.

Mantén abierta la ventana hasta terminar. Un lote pequeño reduce el trabajo por petición, pero un solo PDF complejo todavía puede superar la memoria o el tiempo disponible. **memory_limit=512M** ayuda, pero no garantiza que cualquier archivo se pueda procesar.

## Cola para miles de adjuntos

Desde **2026.10.1**, la extensión incluye una cola por consola con progreso en la base de datos, reanudación y fallos identificados. No depende del navegador. El botón Índice de Joomla sigue usando el mecanismo original y no utiliza esta cola.

Requisitos:

- Instala la versión 2026.10.1 o posterior y completa la [configuración](CONFIGURATION.md), con ambos plugins habilitados y descargas seguras.
- Ejecuta los comandos desde la raíz de Joomla, con un usuario del sistema que pueda leer los adjuntos, escribir en la carpeta temporal configurada y acceder a la base de datos.
- PHP CLI 8.3+ con ZIP, DOM, mbstring y la función **proc_open** disponible. Su configuración puede diferir de PHP web.
- Ejecuta la cola desde un solo servidor. Los bloqueos no coordinan varios nodos.
- No ejecutes simultáneamente el indexador web, finder:index, importaciones ni otros procesos de indexación de Finder: Joomla comparte sus tablas auxiliares. Para una carga inicial grande, utiliza una ventana sin ediciones que disparen indexación automática.

La extensión no crea tareas programadas: ejecuta los tramos manualmente o mediante el planificador de tu servidor.

## Preparar y procesar

~~~sh
php cli/joomla.php attachments:index enqueue
php -d memory_limit=512M cli/joomla.php attachments:index run --limit=100 --timeout=60
~~~

**enqueue** añade IDs nuevos sin duplicar ni reiniciar trabajos terminados. Utiliza una consulta SQL para incorporar los IDs elegibles, sin cargar el contenido de los documentos en memoria.

**run** procesa como máximo 100 trabajos en esa ejecución. Cada archivo se procesa en un **proceso PHP separado**, con hasta 60 segundos de tiempo real. El hijo hereda el memory_limit del supervisor. Repite el mismo comando para continuar con los siguientes trabajos.

Los rangos son 1–10000 para --limit y 1–3600 segundos para --timeout. El límite cuenta trabajos por ejecución, no archivos simultáneos: siempre se procesa uno cada vez.

Al terminar cada archivo se guarda su estado y se muestra el ID y resultado. Al final aparecen los contadores:

| Estado | Significado |
| --- | --- |
| pending | Pendiente |
| running | En procesamiento |
| done | Indexado |
| skipped | Omitido: formato no compatible, despublicado o adjunto eliminado |
| failed | Fallido que requiere revisión |
| excluded | Fuera de los niveles de acceso elegidos para indexar |

Un fallo individual no detiene los siguientes trabajos. Si quedan fallidos, run devuelve código 1 para que un planificador pueda detectarlo; código 0 indica que no hay fallidos, aunque todavía pueda quedar trabajo pendiente. Un error de infraestructura, como no poder iniciar o detener el proceso hijo, detiene la ejecución para evitar trabajar sin control.

## Consultar progreso y fallos

Desde otra terminal, incluso mientras trabaja la cola:

~~~sh
php cli/joomla.php attachments:index status
php cli/joomla.php attachments:index failures
~~~

El informe muestra ID, intentos, último código de error y fecha UTC. Devuelve hasta 100 fallos por consulta. Para continuar después del último ID:

~~~sh
php cli/joomla.php attachments:index failures --after=1234
~~~

| Código | Significado |
| --- | --- |
| timeout | Superó el tiempo real máximo y se terminó el hijo |
| worker_failed | El hijo terminó inesperadamente, por ejemplo por error fatal o falta de memoria |
| extraction_failed | No se pudo leer o extraer el documento; revisa formato, permisos y límites |
| empty_text | Sin texto extraíble; puede ser un PDF escaneado que necesita OCR |
| index_failed | Falló la preparación o escritura en Finder; revisa configuración y base de datos |
| interrupted | La ejecución anterior terminó sin confirmar ese archivo |

La cola no guarda nombres, rutas ni texto del documento. Identifica el archivo por su ID en Attachments. Los límites del extractor siguen aplicándose: aumentar el tiempo no hace compatibles archivos corruptos, sobredimensionados o PDF sin texto.

## Reanudar y reintentar

Al volver a ejecutar **run**, no se repiten los trabajos confirmados como terminados. El archivo que estuviera en curso se marca como **interrupted** y se continúa con los pendientes. Si el archivo llegó a indexarse pero faltó confirmar el progreso, podrá repetirse al reintentarlo; actualiza la misma entrada de búsqueda.

Si el supervisor murió y su hijo sigue activo, una nueva ejecución se rechaza hasta que ese hijo termine. No borres los archivos de bloqueo para forzarla. Revisa el proceso en el servidor si no finaliza. El límite de tiempo real depende de que el supervisor siga ejecutándose.

Tras corregir los fallos:

~~~sh
php cli/joomla.php attachments:index retry
php -d memory_limit=512M cli/joomla.php attachments:index run --limit=100 --timeout=60
~~~

**retry** devuelve solo los fallidos a pendientes y conserva el número de intentos. No hay reintentos automáticos que puedan dejar la cola atascada en el mismo PDF. Se conserva el último estado, no un historial de cada intento; guarda el informe anterior si lo necesitas.

## Añadir archivos o reconstruir

Usa **enqueue** para nuevos IDs. Para reprocesar también terminados y omitidos, por ejemplo después de vaciar Finder o actualizar el extractor:

~~~sh
php cli/joomla.php attachments:index refresh
php -d memory_limit=512M cli/joomla.php attachments:index run --limit=100 --timeout=60
~~~

**refresh** no vacía el índice global: pone los terminados y omitidos en pendientes y añade IDs nuevos. Los fallidos requieren **retry**. La cola registra únicamente su propio trabajo: el indexador web y las actualizaciones al guardar no cambian sus contadores.

## Selección de niveles de acceso

Desde 2026.10.2, la cola respeta los [niveles configurados en el plugin Finder](ACCESS_LEVELS.md). Guardar la selección excluye trabajos que ya no correspondan y pone en pendientes los nuevos incluidos. Para forzar la sincronización sin extraer documentos:

~~~sh
php cli/joomla.php attachments:index sync
~~~

## Almacenamiento y permisos

La instalación o la primera ejecución crea **#__attachmentscontent_queue**, con el prefijo real de la base de datos. Guarda una fila pequeña por ID: estado, intentos, código de error y fecha. No duplica PDF/DOCX ni su texto. La tabla **#__attachmentscontent_state** guarda una huella de la selección sincronizada. La indexación sigue usando Finder y sus controles de acceso; la consola no hace públicos los documentos.

Los bloqueos se guardan en la carpeta temporal de Joomla y no contienen documentos. No los elimines mientras haya procesos activos. La tabla de progreso se conserva al desinstalar para permitir reanudar; puedes eliminarla manualmente cuando ya no la necesites. Después de una reinstalación o de vaciar Finder, usa **refresh** para reconstruir.

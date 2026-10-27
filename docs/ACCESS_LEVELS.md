# Elegir qué niveles de acceso se indexan

Disponible desde **2026.10.2**. Esta selección controla el contenido que entra en el buscador; no concede ni retira permisos de lectura o descarga.

## Configuración

1. Abre **Sistema → Plugins → Búsqueda inteligente - Attachments Content Search**.
2. En **Niveles de acceso que se indexan**, elige:
   - **Todos los niveles**: valor inicial, conserva el comportamiento anterior.
   - **Solo los niveles seleccionados**: permite elegir varios niveles de Joomla en **Niveles incluidos**.
3. Guarda el plugin.

En el modo de selección, dejar la lista vacía significa **no indexar ningún adjunto**. Para volver al comportamiento general, elige explícitamente **Todos los niveles**.

Se compara el **nivel asignado al adjunto**, no su grupo ni el nivel del artículo. Por ejemplo, seleccionar Registrados e Intranet permite indexar los adjuntos con esos niveles. Un usuario sigue necesitando permiso para el adjunto, el artículo y las categorías superiores para encontrarlo y descargarlo.

Los IDs de los niveles no forman una jerarquía: seleccionar un nivel con ID mayor no incluye automáticamente los demás. Si creas un nivel nuevo, debes seleccionarlo, salvo que estés usando Todos.

## Qué ocurre al guardar

- La nueva selección se aplica inmediatamente a las búsquedas, incluidos sus totales y paginación.
- Se retiran del índice las entradas de adjuntos excluidos y sus relaciones de términos y taxonomías. Se conservan las palabras compartidas con documentos que siguen incluidos y con otros contenidos de Joomla.
- Los trabajos de la cola fuera de la selección pasan a **excluded**, conservando sus intentos.
- Los niveles recién incluidos vuelven a **pending**. Los trabajos terminados que siguen incluidos no se reinician; los fallidos que siguen incluidos conservan su estado.
- Los nuevos IDs incluidos se añaden a la cola sin duplicados.

Guardar **no extrae todos los PDF/DOCX** ni arranca un proceso en segundo plano. Los nuevos incluidos necesitan ejecutar la cola o una indexación de Finder para aparecer.

~~~sh
php -d memory_limit=512M cli/joomla.php attachments:index run --limit=100 --timeout=60
~~~

La indexación normal, las actualizaciones al guardar adjuntos y la cola consultan la selección actual. Los documentos excluidos no vuelven a entrar por utilizar otro de esos mecanismos.

## Si hay un archivo en procesamiento

Los resultados excluidos se ocultan inmediatamente. Para no borrar mientras Finder está escribiendo, la limpieza física se difiere hasta que termine el archivo activo. El plugin muestra un aviso cuando queda pendiente.

El proceso comprueba de nuevo la selección y completa la limpieza al finalizar. Si se interrumpe, se vuelve a intentar en la siguiente visita de un administrador con permiso para gestionar plugins, o mediante la consola:

~~~sh
php cli/joomla.php attachments:index sync
php cli/joomla.php attachments:index status
~~~

La limpieza se hace en grupos de hasta 100 entradas del índice y no lee el contenido de los documentos. Si una petición se interrumpe, los grupos ya retirados no necesitan repetirse. No borres archivos de bloqueo para forzarla.

La consola muestra **excluded** como un estado distinto de **failed**: un documento fuera de la selección no es un error de extracción. retry y refresh no deben reincorporarlo; para incluirlo, cambia la selección y guarda.

Si cambias los parámetros directamente en la base de datos, no se dispara el evento de guardado. Ejecuta **attachments:index sync** para sincronizar físicamente el índice y la cola. Las búsquedas y los trabajos consultan igualmente la configuración guardada.

## Comprobación recomendada

1. Indexa dos documentos con una palabra de prueba interior y niveles distintos.
2. Selecciona solo uno de esos niveles y guarda.
3. Con un usuario que pueda leer ambos, comprueba que solo encuentra el incluido, también en los totales.
4. Revisa el estado de la cola: el otro debe figurar como excluded.
5. Amplía la selección y ejecuta run: el otro debe volver a aparecer después de procesarse.
6. Selecciona el modo de lista sin niveles: no debe aparecer ningún adjunto.
7. Vuelve a Todos para recuperar el alcance original.

Un documento excluido de búsqueda puede seguir descargándose desde su artículo si el usuario tiene permisos. Es el comportamiento esperado: esta opción selecciona qué se indexa, mientras que las reglas de lectura siguen siendo las de Joomla y Attachments.

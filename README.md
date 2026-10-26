# Joomla Attachments Content Search

Búsqueda dentro de archivos **DOCX y PDF con texto** de [Attachments](https://github.com/jmcameron/attachments), integrada en la búsqueda inteligente de Joomla. Sin Apache Tika ni servicios externos.

**0.1.0: primera versión para validar en un sitio de pruebas.** Las pruebas automatizadas comprueban extracción, consultas de permisos y el modelo real de búsqueda de Joomla. No sustituyen la prueba de instalación y funcionamiento en tu servidor.

## Requisitos

- Joomla **6.2 o posterior de la serie 6.x**; código contrastado con **6.2.0**. Joomla 5 y 7 no están soportados.
- Attachments **4.2.5** o posterior compatible; código revisado de 4.2.5.
- PHP 8.3+ compatible con Joomla y extensiones ZIP, DOM y mbstring.
- Descargas seguras de Attachments activadas.
- Archivos locales dentro del directorio estándar de Attachments (JPATH_ROOT/attachments).
- Adjuntos asociados a artículos o categorías de com_content.

## Instalación

1. Descarga **pkg_attachmentscontentsearch-0.1.0.zip** de los artefactos de GitHub Actions, o constrúyelo siguiendo las instrucciones inferiores.
2. Instálalo desde **Sistema → Instalar → Extensiones**.
3. Activa **descargas seguras** en Attachments. Comprueba que el servidor impide acceder directamente al directorio de archivos adjuntos. En Nginx/IIS puede ser necesaria una regla equivalente a la protección de Apache.
4. En **Sistema → Plugins**, activa:
   - **Sistema - Attachments Content Search Guard**.
   - **Búsqueda inteligente - Attachments Content Search**.
   - El plugin de contenido **Búsqueda inteligente / Smart Search** de Joomla, para las actualizaciones al guardar.
5. Desactiva el plugin original **Smart Search - Attachments** para evitar resultados duplicados.
6. Vacía el índice de búsqueda inteligente y vuelve a indexar. Revisa tus filtros si Joomla los elimina al vaciar el índice.
7. Utiliza el módulo o menú de **Búsqueda inteligente** de Joomla.

El paquete no se activa automáticamente y muestra un recordatorio de estos pasos. No modifica el código de Joomla ni de Attachments.

## Qué encuentra

Una búsqueda de **pesca** puede encontrar un documento cuyo nombre no contiene esa palabra, pero cuyo interior sí la contiene. Solo se devuelven adjuntos publicados que el usuario pueda leer.

- DOCX: cuerpo, tablas, cabeceras, pies y notas al pie/finales; reconoce palabras divididas entre segmentos de formato.
- PDF: texto extraíble. Los escaneados necesitan OCR, fuera del alcance de esta versión.
- No procesa DOC antiguos, archivos cifrados, enlaces externos ni adjuntos de otros componentes.
- Un documento vacío o que falla al extraer se omite. Se registra únicamente su ID y el tipo de error, sin contenido ni rutas.
- Límite predeterminado: **20 MB por archivo**, configurable hasta 100 MB. XML DOCX descomprimido: 32 MB. Texto: 2 millones de caracteres, truncando el exceso.
- La extracción se realiza en tu servidor. Los PDF complejos pueden necesitar más memoria/tiempo; usa indexación por consola para colecciones grandes.

Se utiliza la tokenización de Smart Search (palabras, frases y sus opciones de coincidencia), no una búsqueda arbitraria por subcadenas.

## Permisos y privacidad

El ZIP contiene dos plugins: **Finder** extrae e indexa el texto; **System Guard** comprueba permisos actuales antes de calcular resultados, totales y páginas.

Se combinan el nivel de acceso y estado del adjunto; el acceso, estado y fechas de publicación del artículo; y el acceso y publicación de su categoría y todas las categorías superiores. Se usan los niveles de visualización autorizados de Joomla. No se compara su valor numérico. Las reglas de otros componentes o personalizaciones de permisos ajenas a esos niveles no están integradas.

Un cambio de permisos, borrado o retirada de publicación se aplica a la siguiente petición, aunque no se haya reconstruido el índice. Una ampliación de acceso puede requerir indexar si el documento nunca llegó a indexarse.

Las entradas utilizan acceso reservado **0**, invisible al buscador estándar cuando el protector está desactivado. Con el protector activo, tampoco se muestran si Attachments, el plugin Finder o las descargas seguras están desactivados.

Se desactivan las sugerencias automáticas y propuestas de búsqueda alternativa de Smart Search en todo el sitio, incluidos sus endpoints JSON, para evitar revelar palabras restringidas. Las búsquedas normales siguen funcionando.

Las páginas de búsqueda y descargas se excluyen de la caché de página de Joomla y envían **Cache-Control: private, no-store**. Se conserva la caché de otras páginas. Un proxy/CDN o caché de terceros debe respetar estas exclusiones. Vacía cualquier caché de búsqueda anterior al instalar.

Al descargar un DOCX/PDF local de com_content se comprueban otra vez el adjunto y sus padres. Attachments entrega el archivo mediante su controlador habitual. No se publican rutas físicas ni copias de texto.

El índice contiene texto extraído: protégelo junto a la base de datos y sus copias de seguridad.

## Actualización del índice

Los eventos de guardado, borrado y cambio de estado de Attachments actualizan sus entradas si está activo el plugin de contenido de Smart Search. Al sustituir un archivo se elimina primero el texto antiguo; si falla la extracción no se conserva ese contenido.

Para importaciones o cambios efectuados directamente en disco/base de datos:

    php cli/joomla.php finder:index

Los cambios externos de contenido requieren reindexar; los permisos y publicación se comprueban en cada búsqueda.

## Construir el ZIP

    composer install --no-dev --prefer-dist
    php tools/build.php

El instalador se genera en dist/pkg_attachmentscontentsearch-0.1.0.zip con sus dependencias. No hace falta Composer en el servidor de destino.

## Pruebas

    composer install --no-dev
    php tests/extraction.php

Para las pruebas de integración, con SQLite en PHP:

    git clone --depth 1 --branch 6.2.0 https://github.com/joomla/joomla-cms.git ../joomla-cms
    composer install --no-dev --working-dir=../joomla-cms
    php tests/access.php ../joomla-cms

GitHub Actions ejecuta las pruebas en PHP 8.3 y 8.4 y publica el ZIP como artefacto.

### Validación en tu Joomla

- Sube DOCX y PDF con la palabra **pesca** solo en su interior.
- Prueba con visitante, usuario autorizado y usuario de otro grupo.
- Comprueba títulos, fragmentos, totales, páginas y descargas.
- Retira acceso al adjunto sin reindexar: debe desaparecer y denegar su descarga.
- Repite con el artículo, categoría y categoría superior.
- Sustituye el archivo y verifica que desaparecen las palabras antiguas.
- Prueba borrado, despublicación, artículos programados/caducados y caché.
- Comprueba que no aparecen términos restringidos en sugerencias.
- Desactiva el protector: deben desaparecer los resultados del complemento.
- Comprueba que la URL física del archivo no permite saltarse Attachments.

## Licencia

GPL-3.0-or-later. [smalot/pdfparser](https://github.com/smalot/pdfparser) usa LGPL-3.0 y se distribuye con su licencia en vendor. Las demás dependencias conservan sus licencias.

Proyecto independiente; no es una publicación oficial de Joomla ni de Attachments.

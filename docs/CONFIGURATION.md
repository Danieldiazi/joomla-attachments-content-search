# Instalación y configuración completa

## 1. Preparar Joomla

- Joomla 6.2+ de la serie 6 y Attachments 4.2.5 o posterior compatible.
- PHP 8.3+ con ZIP, DOM y mbstring.
- Para PDF complejos, configura **memory_limit=512M** en el php.ini del servidor web. Guarda el archivo y reinicia el servicio PHP/web correspondiente. [Instrucciones y verificación](PHP_MEMORY.md).
- Comprueba el valor efectivo en Sistema → Información del sistema → Información de PHP. El plugin no modifica php.ini.

## 2. Descargar e instalar

1. Abre [Releases](https://github.com/Danieldiazi/joomla-attachments-content-search/releases) y descarga el ZIP **pkg_attachmentscontentsearch-VERSION.zip** que aparece en Assets. Los archivos automáticos Source code no son instaladores de Joomla.
2. En Joomla, abre **Sistema → Instalar → Extensiones** y sube ese ZIP.
3. Espera el mensaje de instalación correcta. El paquete instala los plugins Finder y System Guard, con sus dependencias PDF incluidas.

## 3. Permitir subir DOCX y PDF

En **Contenido → Multimedia → Opciones**, revisa:

- **Extensiones permitidas**: incluye docx y pdf, conservando los formatos existentes.
- **Extensiones de documentos permitidas (tipos de archivo)**: incluye docx y pdf.
- **Tipos de MIME permitidos**: incluye application/pdf y application/vnd.openxmlformats-officedocument.wordprocessingml.document.
- Mantén activadas las comprobaciones de MIME y las restricciones de subidas.

Si Joomla muestra Illegal file Mime type al subir un DOCX, añade su MIME exacto; no desactives las comprobaciones para resolverlo.

## 4. Activar descargas seguras

1. Abre **Componentes → Attachments → Opciones**.
2. En **Security**, activa **Secure attachment downloads**.
3. Guarda los cambios.
4. Comprueba con un documento de prueba que su URL física bajo /attachments no permite descargarlo directamente. En el entorno validado, Apache devolvió 403.

Los enlaces deben pasar por el controlador de Attachments. Configura la protección equivalente si usas otro servidor web.

## 5. Activar los plugins

En **Sistema → Plugins**:

- Habilita **Sistema - Attachments Content Search Guard**.
- Habilita **Búsqueda inteligente - Attachments Content Search**.
- Habilita **Contenido - Búsquedas inteligentes / Smart Search**, incluido en Joomla, para las actualizaciones al guardar.
- Deshabilita el plugin original **Smart Search - Attachments**, para evitar indexaciones duplicadas.
- Deja el acceso de los dos plugins nuevos en **Public**: necesitan ejecutarse también cuando busca un visitante. Son los permisos de cada documento los que filtran sus resultados.

El paquete no habilita automáticamente los plugins.

## 6. Configurar grupos y niveles de acceso

1. Crea o revisa tus grupos en **Usuarios → Grupos**.
2. En **Usuarios → Niveles de acceso**, define qué grupos pueden leer cada nivel.
3. Asigna los usuarios a los grupos correspondientes.
4. Asigna el nivel adecuado a cada adjunto y, si procede, a su artículo y categoría.

Un usuario debe tener acceso al adjunto y a sus padres. Por ejemplo, Registered en el archivo no basta si el artículo o una categoría superior tiene un nivel que no incluye al usuario.

## 7. Publicar los documentos

Al subir un archivo en Attachments:

- Selecciona un artículo o categoría de com_content.
- Sube un DOCX o un PDF con texto seleccionable.
- Marca **Published: Sí**.
- Selecciona su nivel de **Acceso** y guarda.

Un artículo publicado no publica automáticamente sus adjuntos. Un adjunto despublicado no aparece en las búsquedas. Los PDF escaneados sin texto necesitan OCR, no incluido.

El almacenamiento compatible es el directorio estándar de Attachments dentro de Joomla. No se indexan enlaces externos ni adjuntos de otros componentes.

## 8. Elegir coincidencia de palabras

En **Componentes → Búsqueda inteligente → Opciones → Búsqueda inteligente → Coincidencia de palabras**, selecciona:

**Coincidencia de palabras que comienzan con el término de búsqueda**.

Así, personal encuentra también personales. Este ajuste se aplica a la siguiente consulta sin reconstruir el índice. [Explicación de los modos](SEARCH_BEHAVIOUR.md).

El protector desactiva las sugerencias automáticas y las propuestas de búsqueda alternativa para evitar revelar palabras de documentos restringidos.

## 9. Indexar el contenido existente

1. Abre **Componentes → Búsqueda inteligente**.
2. Si utilizabas otro adaptador de Attachments, limpia el índice antes de reconstruirlo; Joomla puede eliminar filtros al vaciarlo, por lo que debes revisarlos. Vaciar el índice no borra artículos ni archivos.
3. Pulsa **Índice** y espera a que termine.
4. Comprueba que aparece el tipo **Attachment document** y los títulos de tus documentos.

Los archivos existentes necesitan esta primera indexación. Las nuevas subidas, sustituciones, borrados y cambios de estado se actualizan mediante los eventos de Attachments cuando está activo el plugin de contenido de Smart Search.

Para importaciones o cambios directos en disco/base de datos, ejecuta de nuevo el índice. Los cambios de permisos y publicación se consultan además durante cada búsqueda.

## 10. Mostrar el buscador

Publica un módulo de tipo **Búsqueda inteligente** o un elemento de menú de ese tipo. No necesitas un módulo propio de este complemento.

Si necesitas que los visitantes puedan buscar documentos públicos, el módulo y el menú deben ser accesibles para esos visitantes. Los resultados privados seguirán filtrados por el usuario conectado.

## 11. Verificar accesos

1. Sube un DOCX público y un PDF reservado a un grupo, con una palabra de prueba solo dentro de los archivos.
2. Como visitante, comprueba que solo aparece el público.
3. Como usuario autorizado, comprueba que aparece también el privado y que puedes abrirlo.
4. Cambia el acceso del adjunto a un grupo distinto: debe desaparecer para ese usuario y denegar el enlace de descarga.
5. Repite con los permisos del artículo o categoría.
6. Revisa totales y páginas: no deben incluir resultados ocultos.
7. Verifica que la URL física no permite saltarse los permisos.

## 12. Caché, mantenimiento y actualizaciones

- El protector excluye las búsquedas y descargas de la caché de página de Joomla y envía private, no-store. Revisa las reglas de cualquier proxy, CDN o caché de terceros.
- El índice guarda texto extraído y términos compartidos; protege la base de datos y sus copias como los documentos originales.
- Supervisa el crecimiento de las tablas Finder y el tiempo de indexado a medida que añadas documentos.
- Actualiza instalando el ZIP de la nueva release sobre el existente; el paquete utiliza el modo de actualización de Joomla.
- Tras actualizar, revisa los plugins, sus opciones y las notas de la release. Ejecuta una indexación si cambia la extracción de contenido.


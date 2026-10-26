# Coincidencia de palabras en las búsquedas

Attachments Content Search utiliza el modo de coincidencia configurado en la búsqueda inteligente de Joomla. Extraer el contenido de un documento no implica buscar automáticamente cualquier subcadena.

## Activar búsqueda por inicio de palabra

1. Entra en **Componentes → Búsqueda inteligente**.
2. Abre **Opciones**.
3. En la pestaña **Búsqueda inteligente**, localiza **Coincidencia de palabras**.
4. Selecciona **Coincidencia de palabras que comienzan con el término de búsqueda**.
5. Pulsa **Guardar y cerrar**.

No es necesario reconstruir el índice por cambiar este modo: se aplica al interpretar la siguiente consulta. Es una opción global de Smart Search, no exclusiva de los adjuntos.

## Ejemplos

| Modo | Búsqueda | Qué puede coincidir |
|---|---|---|
| Coincide exactamente | personal | La palabra completa personal, según los términos/raíces que Joomla haya indexado |
| Comienzan con el término | personal | personal, personales, personalidad |
| Comienzan con el término | personales | personales; no amplía automáticamente la consulta a personal |
| Contengan el término en cualquier lugar | pesca | pesca, pescar, repesca |

La coincidencia por prefijo no equivale a resolver sinónimos o todas las variantes gramaticales. El tratamiento lingüístico de raíces depende de la configuración e idioma de Smart Search. Los términos y frases siguen las reglas de su indexador.

Para encontrar personal y personales al buscar personal, usa la opción de inicio de palabra. Si necesitas coincidencias también en el centro de una palabra, elige el modo que contiene el término en cualquier lugar.

Las tres opciones mantienen los mismos controles de lectura del adjunto, artículo y categorías. Cambiar la coincidencia no concede acceso a documentos restringidos.

## Comprobación realizada

En Joomla 6.2.0 se comprobó que, con coincidencia exacta, personal no devolvía un DOCX que solo contenía personales. Al activar la coincidencia por inicio de palabra, el mismo documento apareció sin reconstruir el índice.

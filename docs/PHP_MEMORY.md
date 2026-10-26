# Memoria PHP para indexar PDF

Para PDF complejos recomendamos comenzar con **memory_limit = 512M** y comprobar el consumo con los documentos reales. Es una recomendación operativa, no una garantía para cualquier documento ni un mínimo estricto para archivos pequeños.

## Incidencia comprobada

Durante la validación con Joomla 6.2.0, PHP 8.4 y Apache, la indexación completa devolvió HTTP 500 al procesar un PDF de aproximadamente 1,6 MB. El diagnóstico mostró:

    OutOfMemoryError: Allowed memory size of 134217728 bytes exhausted

La excepción se produjo al descomprimir contenido del PDF en smalot/pdfparser. El tamaño comprimido de un archivo no permite deducir su consumo de memoria.

Después de aumentar el límite efectivo de PHP de 128M a 512M y reiniciar el servicio, la indexación completa terminó y el documento apareció en el índice.

## Recomendación

Edita el **php.ini que carga el servidor web** y cambia la directiva memory_limit existente (por ejemplo, memory_limit=128M) por:

```ini
memory_limit=512M
```

Guarda php.ini y reinicia el servicio web/PHP correspondiente a tu alojamiento para que lea el cambio.

El plugin no modifica automáticamente esta configuración. El administrador del servidor debe aplicarla mediante el procedimiento correspondiente a su alojamiento.

Para reducir el consumo, el plugin descarta el contenido de imágenes al extraer texto, limita la descompresión de cada flujo PDF a 8 MB y libera referencias entre documentos. Estas medidas no proporcionan un aislamiento estricto de memoria o CPU para cualquier archivo.

## Verificación

1. Abre **Sistema → Información del sistema → Información de PHP** en Joomla.
2. Busca **memory_limit** y comprueba el valor local efectivo del servidor web: **512M**.
3. Ejecuta **Componentes → Búsqueda inteligente → Índice**.
4. Comprueba que termina sin HTTP 500 y que los documentos aparecen en el índice.
5. Repite la búsqueda con visitante y usuario autorizado. El cambio de memoria no modifica los permisos de lectura.

El valor de PHP en consola puede diferir del utilizado por el servidor web. No uses memoria ilimitada para resolver este caso; si vuelve a fallar, revisa el error concreto y los recursos disponibles.

[Referencia: directiva memory_limit de PHP](https://www.php.net/manual/en/ini.core.php#ini.memory-limit).

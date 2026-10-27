# Publicar un instalador mediante GitHub Releases

El flujo **Build release installer** se ejecuta cuando se publica una release. Crea el ZIP de instalación de Joomla y lo adjunta a los Assets de esa misma release.

## Pasos

1. Sube los cambios a main y espera a que **Test and package** termine correctamente.
2. En GitHub, abre **Releases → Draft a new release**.
3. Usa la nomenclatura AAAA.MM.N, con tres números, y crea una etiqueta, por ejemplo **v2026.10.2**, apuntando al commit que deseas distribuir. También se admiten etiquetas de prueba como v2026.10.2-rc.1.
4. El commit de la etiqueta debe contener el flujo release.yml y el generador actualizado tools/build.php.
5. Escribe el título y las notas, y pulsa **Publish release**. Guardar un borrador no genera el instalador.
6. En **Actions**, espera a que finalice **Build release installer**.
7. Vuelve a la release y descarga **pkg_attachmentscontentsearch-2026.10.2.zip** de Assets. No uses los ZIP/TAR automáticos de Source code para instalar en Joomla.

## Qué hace la acción

- Descarga el código del commit etiquetado, no el estado posterior de main.
- Instala dependencias de producción con Composer.
- Comprueba sintaxis, extracción y permisos contra el modelo real de Joomla 6.2.0.
- Construye los dos plugins y el paquete exterior, incluyendo el lector PDF.
- Usa la versión de la etiqueta en el nombre del instalador y en los tres manifiestos XML del archivo generado, sin modificar los archivos del repositorio.
- Valida el contenido y las versiones internas del paquete.
- Adjunta el instalador a la release mediante el GITHUB_TOKEN del flujo, con permiso contents: write.

No requiere un token personal. El repositorio debe permitir GitHub Actions y el permiso de escritura solicitado por el trabajo. La acción no instala el paquete automáticamente en tu sitio Joomla.

Si falla, abre el trabajo desde Actions, corrige el problema y vuelve a ejecutarlo sobre el commit adecuado. Una nueva ejecución sustituye el asset con el mismo nombre. Para una nueva versión de código, publica una etiqueta y release nuevas.

## Construcción local opcional

```sh
composer install --no-dev --prefer-dist
php tools/build.php --version=v2026.10.2
php tests/package.php dist/pkg_attachmentscontentsearch-2026.10.2.zip
```

Sin --version, el generador utiliza la versión del manifiesto del paquete. La opción --output-dir permite elegir otra carpeta de salida.


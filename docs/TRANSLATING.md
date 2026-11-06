# Translation / Traducción

[English README](../README.en.md) · [README español](../README.md)

## English

English (en-GB) and Spanish (es-ES) use Joomla's native language loading. There is no separate language selector: use the administrator/site/application language. Joomla falls back to English when no translation is available. Installation preflight loads the package language from the extracted installer, including on first installation.

To contribute another language:

1. Copy every en-GB folder to the new Joomla language tag under the component's admin/language, the module's language, both plugins' language and the package's language folders.
2. Translate values only; keep keys, format placeholders (%s), action names, JSON keys and identifiers unchanged.
3. Declare each language file in its extension manifest. Component files belong to administration/languages; package files use .sys.ini.
4. Run php tests/languages.php and the package tests after php tools/build.php.
5. Install on Joomla and switch the relevant application language. Check filters, empty/error states, pagination, module, installation notices and console help.

User data is not translated. Stored queue states, exit codes, extraction error identifiers and JSON status/failure output are machine-readable and remain unchanged. Finder's existing “Attachment document” taxonomy identifier remains stable; FINDER_TYPE_ATTACHMENT_DOCUMENT supplies its display translation.

## Español

Se utilizan archivos nativos de Joomla para inglés (en-GB) y español (es-ES), con el idioma de la aplicación y respaldo en inglés. No se añade un selector propio. La preinstalación carga los idiomas desde el instalador extraído.

Para añadir un idioma, copia las carpetas en-GB del componente, módulo, plugins y paquete con la nueva etiqueta, traduce los valores conservando claves y marcadores %s, declara los archivos en sus manifiestos y ejecuta las pruebas anteriores. Comprueba en Joomla filtros, errores, paginación, módulo, instalación y ayuda de consola.

Los datos del usuario, estados persistidos, acciones, claves JSON y códigos de salida/error no se traducen.

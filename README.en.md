# Joomla Attachments Content Search

[Español](README.md) · [English](README.en.md)

Search inside **DOCX and text-based PDF files** managed by [Attachments](https://github.com/jmcameron/attachments), integrated with Joomla Smart Search. No Apache Tika or external services.

**2026.11.2: dedicated administration section with filters and an optional concise dashboard summary.** Automated tests cover extraction, access queries and Joomla's real search model. They do not replace installation and functional testing on your server.

## Requirements

- Joomla **6.2 or later in the 6.x series**; code checked against **6.2.0**. Joomla 5 and 7 are not supported.
- Attachments **4.2.5** or a later compatible release; code reviewed against 4.2.5.
- PHP 8.3+ compatible with Joomla, with ZIP, DOM and mbstring.
- Secure downloads enabled in Attachments.
- For complex PDFs, **512 MB PHP memory recommended**. See the [PHP memory guide (Spanish)](docs/PHP_MEMORY.md).
- Local files in the standard Attachments directory (JPATH_ROOT/attachments).
- Attachments associated with com_content articles or categories.

Start with the [complete installation and configuration guide (Spanish)](docs/CONFIGURATION.md), covering formats and MIME types, php.ini, security, plugins, groups, publication, indexing and the search module.

## Installation

1. Download **pkg_attachmentscontentsearch-VERSION.zip** from the Assets of a [release](https://github.com/Danieldiazi/joomla-attachments-content-search/releases), or build it as described below. The Source code archives are not Joomla installers.
2. Install through **System → Install → Extensions**.
3. Enable **secure downloads** in Attachments. Verify that direct access to the attachment directory is blocked. Nginx/IIS may require rules equivalent to Apache's protection.
4. In **System → Plugins**, enable:
   - **System - Attachments Content Search Guard**.
   - **Smart Search - Attachments Content Search**.
   - Joomla's **Content - Smart Search** plugin, to update entries when saving.
5. Disable the original **Smart Search - Attachments** plugin to avoid duplicate results.
6. Clear the Smart Search index and rebuild it. Review your filters if Joomla removes them when clearing the index.
7. Use Joomla's **Smart Search** module or menu item.

The package does not enable itself automatically; it displays a setup reminder. It does not modify Joomla or Attachments source code.

## What it finds

Searching for **fishing** can find a document whose filename does not contain that word but whose contents do. Only published attachments readable by the current user are returned.

- DOCX: body, tables, headers, footers, footnotes and endnotes; words split across formatting runs are recognised.
- PDF: extractable text. Scanned documents require OCR, outside this version's scope.
- Legacy DOC files, encrypted files, external links and attachments from other components are not processed.
- Empty documents and extraction failures are skipped. Logs contain only the attachment ID and error type, without contents or paths.
- Default limit: **20 MB per file**, configurable up to 100 MB. Uncompressed DOCX XML: 32 MB. Text: two million characters; excess text is truncated.
- Extraction runs on your server. Complex PDFs may need more memory/time; use console indexing for large collections.

Smart Search tokenisation is used, including its word, phrase and matching options. To match **personnel** with a word prefix, enable Joomla's word-prefix matching. See the [word matching guide (Spanish)](docs/SEARCH_BEHAVIOUR.md).

## Permissions and privacy

The ZIP includes two plugins: **Finder** extracts and indexes text; **System Guard** checks current permissions before calculating results, totals and pagination.

Checks combine attachment access and publication state; article access, state and publication dates; and access/publication of its category and every ancestor category. Joomla's authorised viewing access levels are used, without comparing their numeric values. Rules from other components or custom permission systems outside these levels are not integrated.

Permission changes, deletion and unpublishing take effect on the next request, even without rebuilding the index. Broader access may require indexing if the document was never indexed.

Entries use reserved access level **0**, invisible to standard search when the guard is disabled. With the guard active, results also disappear when Attachments, the Finder plugin or secure downloads are disabled.

Smart Search automatic suggestions and alternative-query suggestions are disabled throughout the site, including JSON endpoints, to prevent disclosure of restricted words. Normal searches continue to work.

Search and download pages are excluded from Joomla page caching and send **Cache-Control: private, no-store**. Other pages keep their cache. Third-party caches, proxies and CDNs must respect these exclusions. Clear any old search cache after installation.

Downloading a local com_content DOCX/PDF checks the attachment and its parents again. Attachments delivers the file through its normal controller. Physical paths and extracted-text copies are not published.

The index contains extracted text: protect it alongside the database and backups.

## Index updates

Attachments save, delete and state-change events update index entries when Joomla's Content - Smart Search plugin is active. Replacing a file removes the old text first; an extraction failure does not preserve that content.

For imports or direct changes to files/database:

    php cli/joomla.php finder:index

External content changes require reindexing; permissions and publication are checked on every search.

## Indexed access levels

In the Finder plugin, choose **All access levels** or **Only selected access levels**. Saving hides excluded attachments, removes their index entries and updates the queue. Newly included attachments become pending. An empty selection indexes no attachments. See [steps and checks (Spanish)](docs/ACCESS_LEVELS.md).

## Thousands of documents: batches and resumable queue

Joomla indexes in batches. Start with five PDFs per batch, or one for heavy files. Since 2026.10.1, a console queue provides persistent progress, resumption, isolated per-file processes, time limits and failure listings. See [batch indexing and queue (Spanish)](docs/INDEXING.md) for commands and recommendations.

## Building installers with Releases

Publishing a release runs **Build release installer**, which builds and attaches the Joomla ZIP automatically. Use tags such as **v2026.10.3**; the version is applied to both the ZIP filename and internal manifests. See [release instructions (Spanish)](docs/RELEASING.md).

## Building the ZIP

    composer install --no-dev --prefer-dist
    php tools/build.php

The installer is generated at dist/pkg_attachmentscontentsearch-2026.11.2.zip, with its dependencies. Composer is not required on the target server.

## Tests

    composer install --no-dev
    php tests/extraction.php

Integration tests require SQLite in PHP:

    git clone --depth 1 --branch 6.2.0 https://github.com/joomla/joomla-cms.git ../joomla-cms
    composer install --no-dev --working-dir=../joomla-cms
    php tests/access.php ../joomla-cms
    php tests/queue.php ../joomla-cms
    php tests/selection.php ../joomla-cms
    php tests/report.php ../joomla-cms
    php tests/languages.php

GitHub Actions runs tests on PHP 8.3 and 8.4 and publishes the installer as an artifact.

### Validation in your Joomla installation

- Upload DOCX and PDF files containing **fishing** only inside the document.
- Test as a visitor, an authorised user and a user from a different group.
- Check titles, snippets, totals, pagination and downloads.
- Remove attachment access without reindexing: it must disappear and downloads must be denied.
- Repeat for the article, category and ancestor category.
- Replace the file and verify that old words disappear.
- Test deletion, unpublishing, scheduled/expired articles and caching.
- Verify that restricted terms do not appear in suggestions.
- Disable the guard: this extension's results must disappear.
- Verify that the physical file URL cannot bypass Attachments.
- Switch the administrator language between English and Spanish; check component filters, pagination, the summary module and installation notices.

## License

GPL-3.0-or-later. [smalot/pdfparser](https://github.com/smalot/pdfparser) uses LGPL-3.0 and is distributed with its license in vendor. Other dependencies retain their licenses.

Independent project; not an official Joomla or Attachments publication.

## Attachment index administration

Open **Components → Attachments Content Search** for a global per-level summary and paginated listing. Search by filename or article/category title; filter by access, index/queue state and source page type or ID. Links let authorised users view or edit source articles and categories. The section and module require management access to Attachments Content Search, Smart Search and Content. Configure the component's own permissions in Options.

The optional **Attachments Content Search — Summary** module can be published in cpanel. It displays included access levels, indexed/unindexed counts for local article/category PDF/DOCX files, and a link to the full section. Updating an existing module adopts the concise layout.

This administration feature creates no tables and does not modify the index or queue. Indexed means a Smart Search entry exists; it does not guarantee public visibility. Article and ancestor-category permissions still apply to search and download. Do not add queue counts to indexed counts. Files without a queue state are not labelled pending.

## Languages

The interface, installer notices and console help use Joomla language files for **en-GB** and **es-ES**. The application language is used, with Joomla's English fallback. User-created filenames, titles and access-level names are not translated. Console action names, JSON keys, stored states and error codes remain stable for automation.

See the [translation guide](docs/TRANSLATING.md) to add another language. The linked detailed operational guides currently remain in Spanish.

# Architecture

Target: Joomla 6.2.0 / Attachments 4.2.5.

## Source references reviewed

- [Original adapter](https://github.com/jmcameron/attachments/blob/v4.2.5/attachments_finder_plugin/src/Extension/Attachments.php)
- [Attachments schema](https://github.com/jmcameron/attachments/blob/v4.2.5/attachments_component/admin/sql/install.mysql.sql)
- [Attachment ACL](https://github.com/jmcameron/attachments/blob/v4.2.5/attachments_for_content/src/Extension/AttachmentsForContent.php)
- [Download helper](https://github.com/jmcameron/attachments/blob/v4.2.5/attachments_component/site/src/Helper/AttachmentsHelper.php)
- [Finder query](https://github.com/joomla/joomla-cms/blob/6.2.0/components/com_finder/src/Model/SearchModel.php)
- [Extension boot hook](https://github.com/joomla/joomla-cms/blob/6.2.0/libraries/src/Extension/ExtensionManagerTrait.php)
- [MVC factory hook added in 6.2](https://github.com/joomla/joomla-cms/blob/6.2.0/libraries/src/Extension/Service/Provider/MVCFactory.php)

## Why two plugins

A finder adapter assigns one view level to an index record. It cannot represent the intersection of independently restricted attachments, articles and ancestor categories, or immediate revocation after ACL changes. Taking the maximum numeric access ID is incorrect.

Finder indexes under sentinel access 0, with a unique acsattachment URL identity. The system plugin replaces only the frontend Finder Search/Suggestions models and Display controller through the DI extension boot hook. No core or Attachments files are edited.

The guarded SearchModel includes access 0 among candidates, then applies a correlated EXISTS query against current attachment/article/category records. Non-document results retain core ACL/state filters. Old entries from the original Attachments finder adapter are excluded. Counts and pagination use the filtered query.

Only published attachments and published com_content parents qualify. Editors' permissions to inspect unpublished content are not search exceptions.

Page caching is excluded for Finder and Attachments through the page-cache event. Component output caching is disabled by the custom Finder Display controller. Autocomplete models return an empty list; alternative-query suggestions are removed and their display options disabled. Third-party search consumers bypassing the guarded MVC factory are unsupported.

## Lifecycle

Finder save/delete/state events update entries. Indexing removes an existing entry before extracting a replacement. Extraction failures cannot preserve old body text. Full indexing includes unsupported rows to remove old entries after format or parent changes.

Parent ACL and publication are checked during search. Scheduled/full indexing handles imports and out-of-band edits. No per-user text copies, public text files, external extraction calls or extra database tables.

## Limits and validation

PDF extraction runs synchronously during indexing/saves, not per search. File size is bounded but does not provide a strict memory/CPU bound. OCR and external URLs are out of scope. Storage is restricted to Joomla's standard attachments directory.

Automated integration tests execute the actual Joomla 6.2 SearchModel against synthetic SQLite data and term mappings, including totals and paging. They do not install Joomla or exercise the full HTTP/upload/indexer pipeline. Validate the installer, event lifecycle and server download protection on a staging site.

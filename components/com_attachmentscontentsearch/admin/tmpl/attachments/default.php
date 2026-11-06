<?php
/** @license GPL-3.0-or-later */
defined('_JEXEC') or die;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
extract($this->data, EXTR_SKIP);
$user = Factory::getApplication()->getIdentity();
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$link = static function (int $targetLevel, int $targetPage = 1) use ($search, $state, $kind, $parent) {
    return 'index.php?' . http_build_query(['option' => 'com_attachmentscontentsearch', 'level' => $targetLevel, 'page' => $targetPage, 'search' => $search, 'state' => $state, 'kind' => $kind, 'parent' => $parent]);
};
use Joomla\Component\Content\Site\Helper\RouteHelper;
?>
<div class="attachmentscontent-summary">
<form method="get" action="index.php" class="mb-3">
<input type="hidden" name="option" value="com_attachmentscontentsearch">
<div class="row g-2">
<div class="col-md-4"><label for="acs-search"><?= Text::_('COM_ATTACHMENTSCONTENTSEARCH_SEARCH') ?></label><input class="form-control" id="acs-search" name="search" maxlength="200" value="<?= $escape($search) ?>"></div>
<div class="col-md-2"><label for="acs-level"><?= Text::_('COM_ATTACHMENTSCONTENTSEARCH_ACCESS') ?></label><select class="form-select" id="acs-level" name="level"><option value="-1"><?= Text::_('COM_ATTACHMENTSCONTENTSEARCH_ALL') ?></option><?php foreach ($levels as $id => $access): ?><option value="<?= (int) $id ?>" <?= (int) $id === $level ? 'selected' : '' ?>><?= $escape(($access->title ?? Text::sprintf('COM_ATTACHMENTSCONTENTSEARCH_LEVEL_ID', $id))) ?></option><?php endforeach; ?></select></div>
<div class="col-md-2"><label for="acs-state"><?= Text::_('COM_ATTACHMENTSCONTENTSEARCH_STATE') ?></label><select class="form-select" id="acs-state" name="state"><?php foreach ([''=>Text::_('COM_ATTACHMENTSCONTENTSEARCH_ALL'),'indexed'=>Text::_('COM_ATTACHMENTSCONTENTSEARCH_INDEXED'),'unindexed'=>Text::_('COM_ATTACHMENTSCONTENTSEARCH_UNINDEXED'),'excluded'=>Text::_('COM_ATTACHMENTSCONTENTSEARCH_EXCLUDED_LEVEL'),'pending'=>Text::_('COM_ATTACHMENTSCONTENTSEARCH_Q_PENDING'),'running'=>Text::_('COM_ATTACHMENTSCONTENTSEARCH_Q_RUNNING'),'failed'=>Text::_('COM_ATTACHMENTSCONTENTSEARCH_Q_FAILED'),'skipped'=>Text::_('COM_ATTACHMENTSCONTENTSEARCH_Q_SKIPPED')] as $value => $label): ?><option value="<?= $escape($value) ?>" <?= $value === $state ? 'selected' : '' ?>><?= $escape($label) ?></option><?php endforeach; ?></select></div>
<div class="col-md-2"><label for="acs-kind"><?= Text::_('COM_ATTACHMENTSCONTENTSEARCH_SOURCE') ?></label><select class="form-select" id="acs-kind" name="kind"><?php foreach ([''=>Text::_('COM_ATTACHMENTSCONTENTSEARCH_ALL_PAGES'),'article'=>Text::_('COM_ATTACHMENTSCONTENTSEARCH_ARTICLE'),'category'=>Text::_('COM_ATTACHMENTSCONTENTSEARCH_CATEGORY')] as $value => $label): ?><option value="<?= $escape($value) ?>" <?= $value === $kind ? 'selected' : '' ?>><?= $escape($label) ?></option><?php endforeach; ?></select></div>
<div class="col-md-2"><label for="acs-parent"><?= Text::_('COM_ATTACHMENTSCONTENTSEARCH_PARENT_ID') ?></label><input class="form-control" type="number" min="0" id="acs-parent" name="parent" value="<?= (int) $parent ?>"></div>
</div><p class="mt-2"><button type="submit" class="btn btn-primary"><?= Text::_('COM_ATTACHMENTSCONTENTSEARCH_FILTER') ?></button> <a class="btn btn-secondary" href="index.php?option=com_attachmentscontentsearch"><?= Text::_('COM_ATTACHMENTSCONTENTSEARCH_CLEAR') ?></a></p></form>

<p><?= Text::_('COM_ATTACHMENTSCONTENTSEARCH_INTRO') ?></p>
<div class="table-responsive"><table class="table table-striped"><caption><?= Text::_('COM_ATTACHMENTSCONTENTSEARCH_SUMMARY') ?></caption><thead><tr><th scope="col"><?= Text::_('COM_ATTACHMENTSCONTENTSEARCH_LEVEL') ?></th><th scope="col"><?= Text::_('COM_ATTACHMENTSCONTENTSEARCH_CONFIG') ?></th><th scope="col"><?= Text::_('COM_ATTACHMENTSCONTENTSEARCH_FILES') ?></th><th scope="col"><?= Text::_('COM_ATTACHMENTSCONTENTSEARCH_INDEXED_FILES') ?></th><th scope="col"><?= Text::_('COM_ATTACHMENTSCONTENTSEARCH_PENDING_FILES') ?></th><th scope="col"><?= Text::_('COM_ATTACHMENTSCONTENTSEARCH_RUNNING') ?></th><th scope="col"><?= Text::_('COM_ATTACHMENTSCONTENTSEARCH_FAILED_FILES') ?></th><th scope="col"><?= Text::_('COM_ATTACHMENTSCONTENTSEARCH_SKIPPED_FILES') ?></th></tr></thead><tbody>
<?php foreach ($levels as $id => $access): $s = $summary[$id] ?? null; ?>
<tr><th scope="row"><a href="<?= $escape($link((int) $id)) ?>"><?= $escape(($access->title ?? Text::sprintf('COM_ATTACHMENTSCONTENTSEARCH_LEVEL_ID', $id))) ?></a></th><td><?= $allowed($id) ? Text::_('COM_ATTACHMENTSCONTENTSEARCH_INCLUDED') : Text::_('COM_ATTACHMENTSCONTENTSEARCH_EXCLUDED_INDEX') ?></td><?php foreach (['total','indexed_count','pending','running','failed','skipped'] as $field): ?><td><?= (int) ($s->$field ?? 0) ?></td><?php endforeach; ?></tr>
<?php endforeach; ?></tbody></table></div>
<p><?= Text::_('COM_ATTACHMENTSCONTENTSEARCH_COUNTS_HELP') ?></p>
<?php if (!$hasQueue): ?><p><?= Text::_('COM_ATTACHMENTSCONTENTSEARCH_NO_QUEUE') ?></p><?php endif; ?>
<p><a href="<?= $escape($link(-1)) ?>"><?= Text::_('COM_ATTACHMENTSCONTENTSEARCH_ALL_LEVELS') ?></a> · <?= $escape(Text::sprintf('COM_ATTACHMENTSCONTENTSEARCH_PAGINATION', (int) $count, (int) $page, (int) $pages)) ?></p>
<div class="table-responsive"><table class="table table-striped"><caption><?= Text::_('COM_ATTACHMENTSCONTENTSEARCH_DETAILS') ?></caption><thead><tr><th scope="col"><?= Text::_('COM_ATTACHMENTSCONTENTSEARCH_ATTACHMENT') ?></th><th scope="col"><?= Text::_('COM_ATTACHMENTSCONTENTSEARCH_ACCESS') ?></th><th scope="col"><?= Text::_('COM_ATTACHMENTSCONTENTSEARCH_STATE') ?></th><th scope="col"><?= Text::_('COM_ATTACHMENTSCONTENTSEARCH_PARENT') ?></th><th scope="col"><?= Text::_('COM_ATTACHMENTSCONTENTSEARCH_PARENT_ACCESS') ?></th><th scope="col"><?= Text::_('COM_ATTACHMENTSCONTENTSEARCH_ACTIONS') ?></th></tr></thead><tbody>
<?php foreach ($rows as $row):
$article = $row->parent_entity === 'article';
$title = $article ? $row->article_title : $row->category_title;
$parentAccess = $article ? $row->article_access : $row->category_access;
$states = ['pending'=>Text::_('COM_ATTACHMENTSCONTENTSEARCH_PENDING'),'running'=>Text::_('COM_ATTACHMENTSCONTENTSEARCH_RUNNING'),'failed'=>Text::_('COM_ATTACHMENTSCONTENTSEARCH_FAILED'),'skipped'=>Text::_('COM_ATTACHMENTSCONTENTSEARCH_SKIPPED'),'done'=>Text::_('COM_ATTACHMENTSCONTENTSEARCH_DONE'),'excluded'=>Text::_('COM_ATTACHMENTSCONTENTSEARCH_EXCLUDED')];
$state = ($row->indexed_count ? Text::_('COM_ATTACHMENTSCONTENTSEARCH_INDEXED') : Text::_('COM_ATTACHMENTSCONTENTSEARCH_NO_ENTRY'));
$state = $row->queue_status ? Text::sprintf('COM_ATTACHMENTSCONTENTSEARCH_QUEUE_STATE', $state, $states[$row->queue_status] ?? Text::_('COM_ATTACHMENTSCONTENTSEARCH_UNKNOWN')) : $state;
?>
<tr><td><?= $escape($row->filename) ?></td><td><?= $escape($levels[$row->access]->title ?? (Text::sprintf('COM_ATTACHMENTSCONTENTSEARCH_LEVEL_ID', $row->access))) ?></td><td><?= $escape($state) ?></td><td><?= $escape($title ?? Text::_('COM_ATTACHMENTSCONTENTSEARCH_UNAVAILABLE')) ?> (<?= $article ? Text::_('COM_ATTACHMENTSCONTENTSEARCH_ARTICLE') : Text::_('COM_ATTACHMENTSCONTENTSEARCH_CATEGORY') ?>)</td><td><?= $escape($levels[$parentAccess]->title ?? ($parentAccess === null ? '—' : Text::sprintf('COM_ATTACHMENTSCONTENTSEARCH_LEVEL_ID', $parentAccess))) ?></td><td>
<?php if ($title !== null):
$route = $article ? RouteHelper::getArticleRoute((int) $row->parent_id, (int) $row->catid, $row->article_language) : RouteHelper::getCategoryRoute((int) $row->parent_id, $row->category_language);
$public = Route::link('site', $route, false);
$asset = $article ? 'com_content.article.' : 'com_content.category.';
$edit = $article ? 'index.php?option=com_content&task=article.edit&id=' : 'index.php?option=com_categories&task=category.edit&extension=com_content&id=';
?><a href="<?= $escape($public) ?>" target="_blank" rel="noopener noreferrer"><?= Text::_('COM_ATTACHMENTSCONTENTSEARCH_VIEW') ?></a>
<?php if ($user->authorise('core.edit', $asset . (int) $row->parent_id)): ?> · <a href="<?= $escape(Route::_($edit . (int) $row->parent_id, false)) ?>"><?= Text::_($article ? 'COM_ATTACHMENTSCONTENTSEARCH_EDIT_ARTICLE' : 'COM_ATTACHMENTSCONTENTSEARCH_EDIT_CATEGORY') ?></a><?php endif; endif; ?></td></tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="6"><?= Text::_('COM_ATTACHMENTSCONTENTSEARCH_EMPTY') ?></td></tr><?php endif; ?>
</tbody></table></div>
<nav aria-label="<?= $escape(Text::_('COM_ATTACHMENTSCONTENTSEARCH_PAGES')) ?>"><?php if ($page > 1): ?><a href="<?= $escape($link($level, $page - 1)) ?>"><?= Text::_('COM_ATTACHMENTSCONTENTSEARCH_PREVIOUS') ?></a> <?php endif; ?><?php if ($page < $pages): ?><a href="<?= $escape($link($level, $page + 1)) ?>"><?= Text::_('COM_ATTACHMENTSCONTENTSEARCH_NEXT') ?></a><?php endif; ?></nav>
<p><?= Text::_('COM_ATTACHMENTSCONTENTSEARCH_VIEW_HELP') ?></p>
</div>

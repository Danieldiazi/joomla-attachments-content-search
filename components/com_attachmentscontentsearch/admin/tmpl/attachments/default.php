<?php
/** @license GPL-3.0-or-later */
defined('_JEXEC') or die;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Factory;
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
<div class="col-md-4"><label for="acs-search">Archivo, artículo o categoría</label><input class="form-control" id="acs-search" name="search" maxlength="200" value="<?= $escape($search) ?>"></div>
<div class="col-md-2"><label for="acs-level">Acceso del adjunto</label><select class="form-select" id="acs-level" name="level"><option value="-1">Todos</option><?php foreach ($levels as $id => $access): ?><option value="<?= (int) $id ?>" <?= (int) $id === $level ? 'selected' : '' ?>><?= $escape($access->title) ?></option><?php endforeach; ?></select></div>
<div class="col-md-2"><label for="acs-state">Estado</label><select class="form-select" id="acs-state" name="state"><?php foreach ([''=>'Todos','indexed'=>'Indexado','unindexed'=>'Sin indexar','excluded'=>'Nivel excluido','pending'=>'Cola: pendiente','running'=>'Cola: en proceso','failed'=>'Cola: fallido','skipped'=>'Cola: omitido'] as $value => $label): ?><option value="<?= $escape($value) ?>" <?= $value === $state ? 'selected' : '' ?>><?= $escape($label) ?></option><?php endforeach; ?></select></div>
<div class="col-md-2"><label for="acs-kind">Página de origen</label><select class="form-select" id="acs-kind" name="kind"><?php foreach ([''=>'Todas','article'=>'Artículo','category'=>'Categoría'] as $value => $label): ?><option value="<?= $escape($value) ?>" <?= $value === $kind ? 'selected' : '' ?>><?= $escape($label) ?></option><?php endforeach; ?></select></div>
<div class="col-md-2"><label for="acs-parent">ID de la página</label><input class="form-control" type="number" min="0" id="acs-parent" name="parent" value="<?= (int) $parent ?>"></div>
</div><p class="mt-2"><button type="submit" class="btn btn-primary">Filtrar</button> <a class="btn btn-secondary" href="index.php?option=com_attachmentscontentsearch">Limpiar</a></p></form>

<p>Archivos PDF y DOCX asociados a artículos y categorías. Agrupación por el acceso del adjunto. El artículo y todas sus categorías pueden restringir adicionalmente su visualización.</p>
<div class="table-responsive"><table class="table table-striped"><caption>Resumen global por nivel de acceso del adjunto (sin filtros)</caption><thead><tr><th scope="col">Nivel</th><th scope="col">Configuración</th><th scope="col">Archivos</th><th scope="col">Indexados</th><th scope="col">Pendientes</th><th scope="col">En proceso</th><th scope="col">Fallidos</th><th scope="col">Omitidos</th></tr></thead><tbody>
<?php foreach ($levels as $id => $access): $s = $summary[$id] ?? null; ?>
<tr><th scope="row"><a href="<?= $escape($link((int) $id)) ?>"><?= $escape($access->title) ?></a></th><td><?= $allowed($id) ? 'Incluido' : 'Excluido de la indexación' ?></td><?php foreach (['total','indexed_count','pending','running','failed','skipped'] as $field): ?><td><?= (int) ($s->$field ?? 0) ?></td><?php endforeach; ?></tr>
<?php endforeach; ?></tbody></table></div>
<p>Indexados cuenta entradas existentes en Smart Search. Los estados de cola pueden coincidir con archivos ya indexados que esperan actualizarse; no se deben sumar. Los archivos sin entrada ni estado de cola no se consideran pendientes.</p>
<?php if (!$hasQueue): ?><p>La cola todavía no está disponible; sus estados se muestran a cero.</p><?php endif; ?>
<p><a href="<?= $escape($link(-1)) ?>">Todos los niveles</a> · <?= (int) $count ?> archivos · Página <?= $page ?> de <?= $pages ?></p>
<div class="table-responsive"><table class="table table-striped"><caption>Adjuntos y páginas que los contienen</caption><thead><tr><th scope="col">Adjunto</th><th scope="col">Acceso del adjunto</th><th scope="col">Estado</th><th scope="col">Artículo o categoría</th><th scope="col">Acceso del artículo / categoría</th><th scope="col">Acciones</th></tr></thead><tbody>
<?php foreach ($rows as $row):
$article = $row->parent_entity === 'article';
$title = $article ? $row->article_title : $row->category_title;
$parentAccess = $article ? $row->article_access : $row->category_access;
$states = ['pending'=>'Pendiente','running'=>'En proceso','failed'=>'Fallido','skipped'=>'Omitido','done'=>'Procesado','excluded'=>'Excluido'];
$state = ($row->indexed_count ? 'Indexado' : 'Sin entrada en el índice') . ($row->queue_status ? ' · Cola: ' . ($states[$row->queue_status] ?? 'Desconocido') : '');
?>
<tr><td><?= $escape($row->filename) ?></td><td><?= $escape($levels[$row->access]->title ?? ('Nivel #' . $row->access)) ?></td><td><?= $escape($state) ?></td><td><?= $escape($title ?? 'Página no disponible') ?> (<?= $article ? 'Artículo' : 'Categoría' ?>)</td><td><?= $escape($levels[$parentAccess]->title ?? ($parentAccess === null ? '—' : 'Nivel #' . $parentAccess)) ?></td><td>
<?php if ($title !== null):
$route = $article ? RouteHelper::getArticleRoute((int) $row->parent_id, (int) $row->catid, $row->article_language) : RouteHelper::getCategoryRoute((int) $row->parent_id, $row->category_language);
$public = Route::link('site', $route, false);
$asset = $article ? 'com_content.article.' : 'com_content.category.';
$edit = $article ? 'index.php?option=com_content&task=article.edit&id=' : 'index.php?option=com_categories&task=category.edit&extension=com_content&id=';
?><a href="<?= $escape($public) ?>" target="_blank" rel="noopener noreferrer">Ver página</a>
<?php if ($user->authorise('core.edit', $asset . (int) $row->parent_id)): ?> · <a href="<?= $escape(Route::_($edit . (int) $row->parent_id, false)) ?>">Editar <?= $article ? 'artículo' : 'categoría' ?></a><?php endif; endif; ?></td></tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="6">No hay archivos para este nivel.</td></tr><?php endif; ?>
</tbody></table></div>
<nav aria-label="Páginas de adjuntos"><?php if ($page > 1): ?><a href="<?= $escape($link($level, $page - 1)) ?>">Anterior</a> <?php endif; ?><?php if ($page < $pages): ?><a href="<?= $escape($link($level, $page + 1)) ?>">Siguiente</a><?php endif; ?></nav>
<p>Ver página abre la web y respeta los permisos y la publicación. La sesión de administración no concede acceso en la web.</p>
</div>

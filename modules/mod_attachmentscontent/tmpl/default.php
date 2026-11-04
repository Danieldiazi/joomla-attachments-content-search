<?php
/** @license GPL-3.0-or-later */
defined('_JEXEC') or die;
use Joomla\CMS\Router\Route;
use Joomla\Component\Content\Site\Helper\RouteHelper;
?>
<div class="attachmentscontent-summary">
<p>Archivos PDF y DOCX asociados a artículos y categorías. Agrupación por el acceso del adjunto. El artículo y todas sus categorías pueden restringir adicionalmente su visualización.</p>
<div class="table-responsive"><table class="table table-striped"><caption>Resumen por nivel de acceso del adjunto</caption><thead><tr><th scope="col">Nivel</th><th scope="col">Configuración</th><th scope="col">Archivos</th><th scope="col">Indexados</th><th scope="col">Pendientes</th><th scope="col">En proceso</th><th scope="col">Fallidos</th><th scope="col">Omitidos</th></tr></thead><tbody>
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
$asset = $article ? 'com_content.article.' : 'com_categories.category.';
$edit = $article ? 'index.php?option=com_content&task=article.edit&id=' : 'index.php?option=com_categories&task=category.edit&extension=com_content&id=';
?><a href="<?= $escape($public) ?>" target="_blank" rel="noopener noreferrer">Ver página</a>
<?php if ($user->authorise('core.edit', $asset . (int) $row->parent_id)): ?> · <a href="<?= $escape(Route::_($edit . (int) $row->parent_id, false)) ?>">Editar <?= $article ? 'artículo' : 'categoría' ?></a><?php endif; endif; ?></td></tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="6">No hay archivos para este nivel.</td></tr><?php endif; ?>
</tbody></table></div>
<nav aria-label="Páginas de adjuntos"><?php if ($page > 1): ?><a href="<?= $escape($link($level, $page - 1)) ?>">Anterior</a> <?php endif; ?><?php if ($page < $pages): ?><a href="<?= $escape($link($level, $page + 1)) ?>">Siguiente</a><?php endif; ?></nav>
<p>Ver página abre la web y respeta los permisos y la publicación. La sesión de administración no concede acceso en la web.</p>
</div>

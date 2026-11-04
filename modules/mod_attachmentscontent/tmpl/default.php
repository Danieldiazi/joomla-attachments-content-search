<?php
/** @license GPL-3.0-or-later */
defined('_JEXEC') or die;
$names = [];
foreach ($data['levels'] as $id => $level) { if (($data['allowed'])($id)) { $names[] = $level->title; } }
?>
<p><strong>Niveles incluidos:</strong> <?= $escape($names ? implode(', ', $names) : 'Ninguno') ?></p>
<p>PDF/DOCX de artículos y categorías:</p>
<p><strong>Indexados:</strong> <?= (int) $data['indexedTotal'] ?> · <strong>Sin indexar:</strong> <?= (int) ($data['total'] - $data['indexedTotal']) ?></p>
<p><a href="index.php?option=com_attachmentscontentsearch">Ver adjuntos y detalles</a></p>

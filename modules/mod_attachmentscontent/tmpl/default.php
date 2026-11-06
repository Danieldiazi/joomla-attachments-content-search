<?php
/** @license GPL-3.0-or-later */
defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
$names = [];
foreach ($data['levels'] as $id => $level) { if (($data['allowed'])($id)) { $names[] = $level->title ?? Text::sprintf('MOD_ATTACHMENTSCONTENT_LEVEL_ID', $id); } }
?>
<p><strong><?= Text::_('MOD_ATTACHMENTSCONTENT_LEVELS') ?></strong> <?= $escape($names ? implode(', ', $names) : Text::_('MOD_ATTACHMENTSCONTENT_NONE')) ?></p>
<p><?= Text::_('MOD_ATTACHMENTSCONTENT_INTRO') ?></p>
<p><strong><?= Text::_('MOD_ATTACHMENTSCONTENT_INDEXED') ?></strong> <?= (int) $data['indexedTotal'] ?> · <strong><?= Text::_('MOD_ATTACHMENTSCONTENT_UNINDEXED') ?></strong> <?= (int) ($data['total'] - $data['indexedTotal']) ?></p>
<p><a href="index.php?option=com_attachmentscontentsearch"><?= Text::_('MOD_ATTACHMENTSCONTENT_DETAILS') ?></a></p>

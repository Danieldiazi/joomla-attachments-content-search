<?php
/** @license GPL-3.0-or-later */
defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\Database\DatabaseInterface;
use Danieldiazi\Component\AttachmentsContentSearch\Administrator\Service\Report;
$app = Factory::getApplication();
if (!$app->isClient('administrator')) { return; }
$helper = JPATH_ADMINISTRATOR . '/components/com_attachmentscontentsearch/src/Service/Report.php';
if (!is_file($helper)) { return; }
require_once $helper;
if (!Report::canView($app->getIdentity())) { return; }
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
try { $data = Report::load(Factory::getContainer()->get(DatabaseInterface::class), [], false); }
catch (\Throwable $error) { echo '<p>' . $escape(Text::_('MOD_ATTACHMENTSCONTENT_ERROR')) . '</p>'; return; }
require ModuleHelper::getLayoutPath('mod_attachmentscontent', 'default');

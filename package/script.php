<?php
/** @license GPL-3.0-or-later */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

class pkg_attachmentscontentsearchInstallerScript
{
    public function preflight($type, $parent): bool
    {
        if ($type === 'uninstall') {
            return true;
        }
        if (version_compare(JVERSION, '6.2.0', '<') || version_compare(JVERSION, '7.0', '>=')
            || version_compare(PHP_VERSION, '8.3.0', '<')) {
            Factory::getApplication()->enqueueMessage('Requires Joomla 6.2.x and PHP 8.3 or later (Joomla 7 is not supported).', 'error');
            return false;
        }
        foreach (['zip', 'dom', 'mbstring'] as $extension) {
            if (!extension_loaded($extension)) {
                Factory::getApplication()->enqueueMessage('Missing PHP extension: ' . $extension, 'error');
                return false;
            }
        }
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->createQuery()->select('manifest_cache')->from('#__extensions')
            ->where('type = ' . $db->quote('component'))->where('element = ' . $db->quote('com_attachments'))->where('enabled = 1');
        $manifest = json_decode((string) $db->setQuery($query)->loadResult(), true);
        if (!$manifest || version_compare($manifest['version'] ?? '0', '4.2.5', '<')) {
            Factory::getApplication()->enqueueMessage('Install Attachments 4.2.5 or later first.', 'error');
            return false;
        }
        return true;
    }

    public function postflight($type, $parent): void
    {
        if ($type === 'uninstall') {
            return;
        }
        require_once JPATH_ROOT . '/plugins/system/attachmentscontentguard/src/Queue/Queue.php';
        (new \Danieldiazi\Plugin\System\AttachmentsContentGuard\Queue\Queue(Factory::getContainer()->get(DatabaseInterface::class)))->install();
        Factory::getApplication()->enqueueMessage(
            'Attachments Content Search: enable secure downloads in Attachments; enable System - Attachments Content Search Guard and Smart Search - Attachments Content Search; disable the original Smart Search - Attachments; clear and rebuild the Smart Search index. See README for the permission test checklist.',
            'notice'
        );
    }
}

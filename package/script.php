<?php
/** @license GPL-3.0-or-later */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

class pkg_attachmentscontentsearchInstallerScript
{
    private function loadLanguage(): void
    {
        // Load from the extracted package, including first-install preflight failures.
        Factory::getApplication()->getLanguage()->load('pkg_attachmentscontentsearch.sys', __DIR__, null, true);
    }

    public function preflight($type, $parent): bool
    {
        $this->loadLanguage();
        if ($type === 'uninstall') {
            return true;
        }
        if (version_compare(JVERSION, '6.2.0', '<') || version_compare(JVERSION, '7.0', '>=')
            || version_compare(PHP_VERSION, '8.3.0', '<')) {
            Factory::getApplication()->enqueueMessage(Text::_('PKG_ATTACHMENTSCONTENTSEARCH_REQUIREMENTS'), 'error');
            return false;
        }
        foreach (['zip', 'dom', 'mbstring'] as $extension) {
            if (!extension_loaded($extension)) {
                Factory::getApplication()->enqueueMessage(Text::sprintf('PKG_ATTACHMENTSCONTENTSEARCH_MISSING_EXTENSION', $extension), 'error');
                return false;
            }
        }
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->createQuery()->select('manifest_cache')->from('#__extensions')
            ->where('type = ' . $db->quote('component'))->where('element = ' . $db->quote('com_attachments'))->where('enabled = 1');
        $manifest = json_decode((string) $db->setQuery($query)->loadResult(), true);
        if (!$manifest || version_compare($manifest['version'] ?? '0', '4.2.5', '<')) {
            Factory::getApplication()->enqueueMessage(Text::_('PKG_ATTACHMENTSCONTENTSEARCH_ATTACHMENTS_REQUIRED'), 'error');
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
            Text::_('PKG_ATTACHMENTSCONTENTSEARCH_SETUP'),
            'notice'
        );
    }
}

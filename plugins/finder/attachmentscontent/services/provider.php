<?php
/** @license GPL-3.0-or-later */
defined('_JEXEC') or die;

use Danieldiazi\Plugin\Finder\AttachmentsContent\Extension\AttachmentsContent;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

require_once dirname(__DIR__) . '/vendor/autoload.php';

return new class implements ServiceProviderInterface {
    public function register(Container $container)
    {
        $container->set(PluginInterface::class, function (Container $container) {
            $plugin = new AttachmentsContent((array) PluginHelper::getPlugin('finder', 'attachmentscontent'));
            $plugin->setApplication(Factory::getApplication());
            $plugin->setDatabase($container->get(DatabaseInterface::class));
            return $plugin;
        });
    }
};

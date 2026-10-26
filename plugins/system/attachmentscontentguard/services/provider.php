<?php
/** @license GPL-3.0-or-later */
defined('_JEXEC') or die;

use Danieldiazi\Plugin\System\AttachmentsContentGuard\Extension\Guard;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class implements ServiceProviderInterface {
    public function register(Container $container)
    {
        $container->set(PluginInterface::class, function (Container $container) {
            $plugin = new Guard((array) PluginHelper::getPlugin('system', 'attachmentscontentguard'));
            $plugin->setApplication(Factory::getApplication());
            $plugin->setDatabase($container->get(DatabaseInterface::class));
            return $plugin;
        });
    }
};

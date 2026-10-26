<?php
/** @license GPL-3.0-or-later */
namespace Danieldiazi\Plugin\System\AttachmentsContentGuard\Factory;

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;

/** Joomla 6.2 factory hook preserves all core dependency injection. */
final class GuardedFactoryProvider extends \Joomla\CMS\Extension\Service\Provider\MVCFactory
{
    protected function createMVCFactory(): MVCFactoryInterface
    {
        return new GuardedFactory($this->getNamespace());
    }
}

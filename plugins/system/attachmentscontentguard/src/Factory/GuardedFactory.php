<?php
/** @license GPL-3.0-or-later */
namespace Danieldiazi\Plugin\System\AttachmentsContentGuard\Factory;

use Danieldiazi\Plugin\System\AttachmentsContentGuard\Model\SearchModel;
use Danieldiazi\Plugin\System\AttachmentsContentGuard\Model\SuggestionsModel;
use Danieldiazi\Plugin\System\AttachmentsContentGuard\Controller\DisplayController;

final class GuardedFactory extends \Joomla\CMS\MVC\Factory\MVCFactory
{
    protected function getClassName(string $suffix, string $prefix)
    {
        if (strtolower($prefix) === 'site') {
            return match ($suffix) {
                'Model\\SearchModel' => SearchModel::class,
                'Model\\SuggestionsModel' => SuggestionsModel::class,
                'Controller\\DisplayController' => DisplayController::class,
                default => parent::getClassName($suffix, $prefix),
            };
        }
        return parent::getClassName($suffix, $prefix);
    }
}

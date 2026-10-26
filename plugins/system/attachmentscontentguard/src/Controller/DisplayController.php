<?php
/** @license GPL-3.0-or-later */
namespace Danieldiazi\Plugin\System\AttachmentsContentGuard\Controller;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Component\Finder\Administrator\Helper\LanguageHelper;

final class DisplayController extends \Joomla\Component\Finder\Site\Controller\DisplayController
{
    public function display($cachable = false, $urlparams = [])
    {
        LanguageHelper::loadPluginLanguage();
        $this->app->getInput()->set('view', $this->app->getInput()->get('view', 'search', 'word'));
        return BaseController::display(false, $urlparams);
    }
}

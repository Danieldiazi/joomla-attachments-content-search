<?php
/** @license GPL-3.0-or-later */
namespace Danieldiazi\Component\AttachmentsContentSearch\Administrator\Controller;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Danieldiazi\Component\AttachmentsContentSearch\Administrator\Service\Report;
defined('_JEXEC') or die;
final class DisplayController extends BaseController
{
    protected $default_view = 'attachments';
    public function display($cachable = false, $urlparams = [])
    {
        if (!Report::canView($this->app->getIdentity())) { throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403); }
        // This component exposes a single read-only view.
        $this->input->set('view', 'attachments');
        return parent::display(false, $urlparams);
    }
}

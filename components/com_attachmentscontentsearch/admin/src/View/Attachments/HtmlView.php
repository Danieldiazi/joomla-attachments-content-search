<?php
/** @license GPL-3.0-or-later */
namespace Danieldiazi\Component\AttachmentsContentSearch\Administrator\View\Attachments;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;
use Danieldiazi\Component\AttachmentsContentSearch\Administrator\Service\Report;
defined('_JEXEC') or die;
final class HtmlView extends BaseHtmlView
{
    public array $data = [];
    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        if (!Report::canView($app->getIdentity())) { throw new \RuntimeException('Acceso no autorizado', 403); }
        ToolbarHelper::title('Attachments Content Search', 'search');
        ToolbarHelper::preferences('com_attachmentscontentsearch');
        $input = $app->getInput();
        try {
            $this->data = Report::load(Factory::getContainer()->get(DatabaseInterface::class), [
                'level' => $input->getInt('level', -1), 'page' => $input->getInt('page', 1),
                'search' => $input->getString('search', ''), 'state' => $input->getCmd('state', ''),
                'kind' => $input->getCmd('kind', ''), 'parent' => $input->getInt('parent', 0),
            ]);
        } catch (\Throwable $error) {
            $app->enqueueMessage('No se pudo consultar el índice. Comprueba que Attachments y Búsqueda inteligente están instalados.', 'error');
            return;
        }
        parent::display($tpl);
    }
}

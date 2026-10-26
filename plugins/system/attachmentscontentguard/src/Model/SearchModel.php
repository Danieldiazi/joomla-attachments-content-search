<?php
/** @license GPL-3.0-or-later */
namespace Danieldiazi\Plugin\System\AttachmentsContentGuard\Model;

use Danieldiazi\Plugin\System\AttachmentsContentGuard\Security\AccessPolicy;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;

final class SearchModel extends \Joomla\Component\Finder\Site\Model\SearchModel
{
    protected $option = 'com_finder';
    protected function populateState($ordering = null, $direction = null)
    {
        parent::populateState($ordering, $direction);
        // Sentinel access 0 is invisible to core Finder if the guard is disabled.
        $this->setState('user.groups', array_merge($this->getCurrentUser()->getAuthorisedViewLevels(), [0]));
        foreach (['included', 'excluded', 'ignored'] as $group) {
            foreach ($this->searchquery->$group as $token) {
                unset($token->suggestion);
            }
        }
    }

    protected function getListQuery()
    {
        $query = parent::getListQuery();
        $enabled = ComponentHelper::isEnabled('com_attachments')
            && PluginHelper::isEnabled('finder', 'attachmentscontent')
            && (bool) ComponentHelper::getParams('com_attachments')->get('secure', false);
        $query->where(AccessPolicy::finderCondition(
            $this->getDatabase(), $this->getCurrentUser()->getAuthorisedViewLevels(),
            Factory::getDate()->toSql(), $enabled
        ));
        return $query;
    }

    protected function getStoreId($id = '', $page = true)
    {
        $id .= ':' . (int) $this->getCurrentUser()->id . ':' . implode(',', $this->getCurrentUser()->getAuthorisedViewLevels());
        return parent::getStoreId($id, $page);
    }
}

<?php
/** @license GPL-3.0-or-later */
namespace Danieldiazi\Plugin\System\AttachmentsContentGuard\Model;

final class SuggestionsModel extends \Joomla\Component\Finder\Site\Model\SuggestionsModel
{
    protected $option = 'com_finder';
    /** The shared dictionary must not reveal restricted words through JSON endpoints. */
    public function getItems()
    {
        return [];
    }
}

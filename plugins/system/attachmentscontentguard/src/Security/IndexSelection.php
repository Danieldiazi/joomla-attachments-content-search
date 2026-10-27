<?php
/** @license GPL-3.0-or-later */
namespace Danieldiazi\Plugin\System\AttachmentsContentGuard\Security;

use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

/** An indexing scope, not a replacement for the current user's view levels. */
final class IndexSelection
{
    public function __construct(private ?array $levels = null) {}
    public static function fromParams(Registry $params): self
    {
        if ($params->get('index_access_mode', 'all') === 'all') {
            return new self();
        }
        $raw = $params->get('index_access_levels', []);
        $levels = [];
        foreach (is_array($raw) ? $raw : [] as $value) {
            if ((is_int($value) || is_string($value)) && preg_match('/^[1-9][0-9]*$/D', (string) $value)
                && (float) $value <= PHP_INT_MAX) {
                $levels[] = (int) $value;
            }
        }
        $levels = array_values(array_unique($levels));
        sort($levels, SORT_NUMERIC);
        return new self($levels);
    }
    public static function load(DatabaseInterface $db): self
    {
        // Read the committed configuration, including changes saved while a worker was running.
        $query = $db->createQuery()->select('params')->from('#__extensions')
            ->where("type = 'plugin'")->where("folder = 'finder'")->where("element = 'attachmentscontent'");
        $params = $db->setQuery($query)->loadResult();
        return $params === null ? new self([]) : self::fromParams(new Registry((string) $params));
    }
    public function allows(int $access): bool
    {
        return $this->levels === null || in_array($access, $this->levels, true);
    }
    public function condition(string $alias = 'a'): string
    {
        if (!preg_match('/^[a-z][a-z0-9_]*$/iD', $alias)) {
            throw new \InvalidArgumentException('Invalid SQL alias');
        }
        return $this->levels === null ? '1 = 1'
            : ($this->levels === [] ? '1 = 0' : $alias . '.access IN (' . implode(',', $this->levels) . ')');
    }
    public function hash(): string
    {
        return hash('sha256', json_encode($this->levels, JSON_THROW_ON_ERROR));
    }
}

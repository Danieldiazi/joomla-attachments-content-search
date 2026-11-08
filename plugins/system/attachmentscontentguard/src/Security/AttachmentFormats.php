<?php
/** @license GPL-3.0-or-later */
namespace Danieldiazi\Plugin\System\AttachmentsContentGuard\Security;

use Joomla\Database\DatabaseInterface;

final class AttachmentFormats
{
    private const MIME_TYPES = [
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'pdf' => 'application/pdf',
        'odt' => 'application/vnd.oasis.opendocument.text',
        'txt' => 'text/plain',
        'csv' => 'text/csv',
        'md' => 'text/markdown',
    ];

    public static function mime(string $filename): ?string
    {
        return self::MIME_TYPES[strtolower(pathinfo($filename, PATHINFO_EXTENSION))] ?? null;
    }

    public static function condition(DatabaseInterface $db): string
    {
        return '(' . implode(' OR ', array_map(
            static fn(string $extension): string => 'LOWER(a.filename) LIKE ' . $db->quote('%.' . $extension),
            array_keys(self::MIME_TYPES),
        )) . ')';
    }
}

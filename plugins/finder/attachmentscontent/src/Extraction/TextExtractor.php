<?php
/** @license GPL-3.0-or-later */
namespace Danieldiazi\Plugin\Finder\AttachmentsContent\Extraction;

use DOMDocument;
use DOMXPath;
use RuntimeException;
use Smalot\PdfParser\Parser;
use ZipArchive;

final class TextExtractor
{
    public function __construct(
        private readonly int $maxBytes = 20971520,
        private readonly int $maxXmlBytes = 33554432,
        private readonly int $maxCharacters = 2000000,
    ) {}

    public function extract(string $filename, string $originalName, string $allowedDirectory): string
    {
        $path = realpath($filename);
        $root = realpath($allowedDirectory);
        if ($path === false || $root === false || !is_file($path)) {
            throw new RuntimeException('FILE_UNAVAILABLE');
        }
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('FILE_UNAVAILABLE');
        }
        fclose($handle);
        $candidate = str_replace('\\', '/', $path);
        $boundary = rtrim(str_replace('\\', '/', $root), '/') . '/';
        if (PHP_OS_FAMILY === 'Windows') {
            $candidate = strtolower($candidate);
            $boundary = strtolower($boundary);
        }
        if (!str_starts_with($candidate, $boundary)) {
            throw new RuntimeException('FILE_OUTSIDE_ATTACHMENTS');
        }
        $size = filesize($path);
        if ($size === false || $size > $this->maxBytes) {
            throw new RuntimeException('FILE_TOO_LARGE');
        }
        $text = match (strtolower(pathinfo($originalName, PATHINFO_EXTENSION))) {
            'docx' => $this->docx($path),
            'pdf' => (new Parser())->parseFile($path)->getText(),
            default => throw new RuntimeException('UNSUPPORTED_FORMAT'),
        };
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        $text = trim(preg_replace('/[\p{Z}\s]+/u', ' ', $text) ?? '');
        return trim(mb_substr($text, 0, $this->maxCharacters, 'UTF-8'));
    }

    private function docx(string $path): string
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('INVALID_DOCX');
        }
        try {
            if ($zip->locateName('word/document.xml') === false) {
                throw new RuntimeException('INVALID_DOCX');
            }
            $used = 0;
            $parts = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (!$stat || !preg_match('~^word/(document|header\d+|footer\d+|footnotes|endnotes)\.xml$~D', $stat['name'])) {
                    continue;
                }
                $used += $stat['size'];
                if ($used > $this->maxXmlBytes || !empty($stat['encryption_method'])) {
                    throw new RuntimeException('DOCX_LIMIT_OR_ENCRYPTION');
                }
                $xml = $zip->getFromIndex($i);
                if ($xml === false || stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
                    throw new RuntimeException('UNSAFE_XML');
                }
                $previous = libxml_use_internal_errors(true);
                try {
                    $dom = new DOMDocument();
                    if (!$dom->loadXML($xml, LIBXML_NONET) || $dom->doctype !== null) {
                        throw new RuntimeException('INVALID_XML');
                    }
                    $xpath = new DOMXPath($dom);
                    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                    $xpath->registerNamespace('s', 'http://purl.oclc.org/ooxml/wordprocessingml/main');
                    $text = '';
                    // Runs can split a word. Only paragraphs, tabs and breaks insert a separator.
                    foreach ($xpath->query('//w:t | //w:tab | //w:br | //w:cr | //w:p | //s:t | //s:tab | //s:br | //s:cr | //s:p') as $node) {
                        $text .= $node->localName === 't' ? $node->textContent : ' ';
                    }
                    $parts[] = $text;
                } finally {
                    libxml_clear_errors();
                    libxml_use_internal_errors($previous);
                }
            }
            return implode(' ', $parts);
        } finally {
            $zip->close();
        }
    }
}

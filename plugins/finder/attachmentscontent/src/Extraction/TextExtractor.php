<?php
/** @license GPL-3.0-or-later */
namespace Danieldiazi\Plugin\Finder\AttachmentsContent\Extraction;

use DOMDocument;
use DOMXPath;
use RuntimeException;
use Smalot\PdfParser\Parser;
use Smalot\PdfParser\Config;
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
            'pdf' => $this->pdf($path),
            'odt' => $this->odt($path),
            'txt', 'md' => $this->plainText($path),
            'csv' => $this->csv($path),
            default => throw new RuntimeException('UNSUPPORTED_FORMAT'),
        };
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        $text = trim(preg_replace('/[\p{Z}\s]+/u', ' ', $text) ?? '');
        return trim(mb_substr($text, 0, $this->maxCharacters, 'UTF-8'));
    }

    private function plainText(string $path): string
    {
        $text = @file_get_contents($path, false, null, 0, $this->maxBytes + 1);
        if ($text === false || str_contains($text, "\0")) {
            throw new RuntimeException('INVALID_TEXT_FILE');
        }
        if (strlen($text) > $this->maxBytes) {
            throw new RuntimeException('FILE_TOO_LARGE');
        }
        if (str_starts_with($text, "\xEF\xBB\xBF")) {
            $text = substr($text, 3);
        }
        return mb_check_encoding($text, 'UTF-8') ? $text : mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
    }

    private function csv(string $path): string
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new RuntimeException('FILE_UNAVAILABLE');
        }
        try {
            $text = $this->plainText($path);
            if (fwrite($stream, $text) !== strlen($text)) {
                throw new RuntimeException('FILE_UNAVAILABLE');
            }
            unset($text);
            rewind($stream);
            $comma = fgetcsv($stream, separator: ',', escape: '');
            rewind($stream);
            $semicolon = fgetcsv($stream, separator: ';', escape: '');
            $separator = count($semicolon ?: []) > count($comma ?: []) ? ';' : ',';
            rewind($stream);
            $text = '';
            while (($row = fgetcsv($stream, separator: $separator, escape: '')) !== false) {
                $text .= implode(' ', $row) . "\n";
                if (strlen($text) >= $this->maxCharacters * 4) {
                    break;
                }
            }
            return $text;
        } finally {
            fclose($stream);
        }
    }

    private function odt(string $path): string
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('INVALID_ODT');
        }
        try {
            $stat = $zip->statName('content.xml');
            if ($stat === false) {
                throw new RuntimeException('INVALID_ODT');
            }
            if ($stat['size'] > $this->maxXmlBytes || !empty($stat['encryption_method'])) {
                throw new RuntimeException('ODT_LIMIT_OR_ENCRYPTION');
            }
            $xml = $zip->getFromName('content.xml', $this->maxXmlBytes + 1);
            if ($xml === false || strlen($xml) > $this->maxXmlBytes) {
                throw new RuntimeException('INVALID_ODT');
            }
            $dom = $this->loadXml($xml);
            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('office', 'urn:oasis:names:tc:opendocument:xmlns:office:1.0');
            $xpath->registerNamespace('text', 'urn:oasis:names:tc:opendocument:xmlns:text:1.0');
            $body = $xpath->query('/office:document-content/office:body/office:text')->item(0);
            if ($body === null) {
                throw new RuntimeException('INVALID_ODT');
            }
            $nodes = $xpath->query(
                './/text:p | .//text:h | .//text:s | .//text:tab | .//text:line-break'
                . ' | .//text()[ancestor::text:p or ancestor::text:h]',
                $body,
            );
            $text = '';
            foreach ($nodes as $node) {
                $text .= $node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE
                    ? $node->nodeValue : ' ';
            }
            return $text;
        } finally {
            $zip->close();
        }
    }

    private function loadXml(string $xml): DOMDocument
    {
        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            throw new RuntimeException('UNSAFE_XML');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new DOMDocument();
            if (!$dom->loadXML($xml, LIBXML_NONET) || $dom->doctype !== null) {
                throw new RuntimeException('INVALID_XML');
            }
            return $dom;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function pdf(string $path): string
    {
        $config = new Config();
        // Search needs text, not decoded image streams, which can exceed PHP memory.
        $config->setRetainImageContent(false);
        $config->setDecodeMemoryLimit(8 * 1024 * 1024);
        $config->setMaxNestingDepth(256);
        $parser = new Parser([], $config);
        $document = null;
        try {
            $document = $parser->parseFile($path);
            return $document->getText();
        } finally {
            // PDF objects reference their document and each other. Release cycles between files.
            unset($document, $parser);
            gc_collect_cycles();
        }
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
                if ($xml === false) {
                    throw new RuntimeException('UNSAFE_XML');
                }
                $dom = $this->loadXml($xml);
                $xpath = new DOMXPath($dom);
                $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                $xpath->registerNamespace('s', 'http://purl.oclc.org/ooxml/wordprocessingml/main');
                $text = '';
                // Runs can split a word. Only paragraphs, tabs and breaks insert a separator.
                foreach ($xpath->query('//w:t | //w:tab | //w:br | //w:cr | //w:p | //s:t | //s:tab | //s:br | //s:cr | //s:p') as $node) {
                    $text .= $node->localName === 't' ? $node->textContent : ' ';
                }
                $parts[] = $text;
            }
            return implode(' ', $parts);
        } finally {
            $zip->close();
        }
    }
}

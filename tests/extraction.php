<?php
require __DIR__ . '/bootstrap.php';
use Danieldiazi\Plugin\Finder\AttachmentsContent\Extraction\TextExtractor;

$dir = dirname(__DIR__) . '/build/fixtures';
@mkdir($dir, 0775, true);
function docx(string $path, string $xml, array $extra = []): void {
    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('word/document.xml', $xml);
    foreach ($extra as $name => $content) {
        $zip->addFromString($name, $content);
    }
    $zip->close();
}
$xml = '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>pes</w:t></w:r><w:r><w:t>ca</w:t></w:r></w:p><w:p><w:r><w:t>marítima &amp; río</w:t></w:r></w:p></w:body></w:document>';
docx($dir . '/fishing.docx', $xml, ['word/header1.xml' => str_replace('marítima &amp; río', 'cabecera', $xml)]);
$extractor = new TextExtractor();
$text = $extractor->extract($dir . '/fishing.docx', 'FISHING.DOCX', $dir);
check(str_contains($text, 'pesca marítima & río'), 'DOCX joins split runs, separates paragraphs and preserves accents');
check(str_contains($text, 'cabecera'), 'DOCX reads headers');
$strict = str_replace('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'http://purl.oclc.org/ooxml/wordprocessingml/main', $xml);
docx($dir . '/strict.docx', $strict);
check(str_contains($extractor->extract($dir . '/strict.docx', 'strict.docx', $dir), 'pesca'), 'Strict OOXML');
rejects(fn() => $extractor->extract($dir . '/fishing.docx', 'fishing.docx', $dir . '/other'), 'Rejects paths outside allowed directory');
rejects(fn() => (new TextExtractor(1))->extract($dir . '/fishing.docx', 'fishing.docx', $dir), 'Rejects oversized file');
rejects(fn() => (new TextExtractor(20971520, 10))->extract($dir . '/fishing.docx', 'fishing.docx', $dir), 'Rejects decompression over XML limit');
docx($dir . '/entity.docx', '<!DOCTYPE document [<!ENTITY secret SYSTEM "file:///etc/passwd">]>' . $xml);
rejects(fn() => $extractor->extract($dir . '/entity.docx', 'entity.docx', $dir), 'Rejects XML entities');
file_put_contents($dir . '/broken.docx', 'not a ZIP');
rejects(fn() => $extractor->extract($dir . '/broken.docx', 'broken.docx', $dir), 'Rejects malformed DOCX');
rejects(fn() => $extractor->extract($dir . '/fishing.docx', 'fishing.doc', $dir), 'Rejects legacy DOC');
check(mb_strlen((new TextExtractor(20971520, 33554432, 5))->extract($dir . '/fishing.docx', 'fishing.docx', $dir)) === 5, 'Bounds extracted text length');

// A real, minimal PDF with a text stream and a valid cross-reference table.
function pdf(string $text): string {
    $stream = "BT /F1 12 Tf 72 720 Td (" . $text . ") Tj ET";
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream",
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
    }
    $start = strlen($pdf);
    $pdf .= "xref\n0 6\n0000000000 65535 f \n";
    foreach (array_slice($offsets, 1) as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }
    return $pdf . "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n" . $start . "\n%%EOF\n";
}
file_put_contents($dir . '/fishing.pdf', pdf('Informe sobre pesca sostenible'));
check(str_contains($extractor->extract($dir . '/fishing.pdf', 'fishing.pdf', $dir), 'pesca sostenible'), 'PDF extracts body text without Tika');
file_put_contents($dir . '/empty.pdf', pdf(''));
check($extractor->extract($dir . '/empty.pdf', 'empty.pdf', $dir) === '', 'PDF without text returns no indexable content');
echo "$testCount extraction checks passed.\n";

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

// Regression: a compressed image can expand far beyond the uploaded PDF size.
$compressor = deflate_init(ZLIB_ENCODING_DEFLATE, ['level' => 9]);
$imageStream = '';
$chunk = str_repeat("\0", 1048576);
for ($i = 0; $i < 192; $i++) {
    $imageStream .= deflate_add($compressor, $chunk, ZLIB_NO_FLUSH);
}
$imageStream .= deflate_add($compressor, '', ZLIB_FINISH);
unset($compressor, $chunk);
$textStream = 'BT /F1 12 Tf 72 720 Td (pesca con imagen comprimida) Tj ET';
$objects = [
    '<< /Type /Catalog /Pages 2 0 R >>',
    '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
    '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> /XObject << /Im1 6 0 R >> >> /Contents 5 0 R >>',
    '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    "<< /Length " . strlen($textStream) . " >>\nstream\n" . $textStream . "\nendstream",
    "<< /Type /XObject /Subtype /Image /Width 8192 /Height 8192 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length "
        . strlen($imageStream) . " >>\nstream\n" . $imageStream . "\nendstream",
];
$pdfData = "%PDF-1.4\n";
$offsets = [];
foreach ($objects as $index => $object) {
    $offsets[] = strlen($pdfData);
    $pdfData .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
}
$xrefOffset = strlen($pdfData);
$pdfData .= "xref\n0 7\n0000000000 65535 f \n";
foreach ($offsets as $offset) {
    $pdfData .= sprintf("%010d 00000 n \n", $offset);
}
$pdfData .= "trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF\n";
file_put_contents($dir . '/compressed-image.pdf', $pdfData);
unset($pdfData, $objects, $imageStream);
check(str_contains($extractor->extract($dir . '/compressed-image.pdf', 'compressed-image.pdf', $dir), 'pesca con imagen'), 'Extracts text without inflating a 192 MB image under a 128 MB PHP limit');
echo "$testCount total extraction checks passed.\n";

// Unavailable files should produce a controlled extraction failure.
try {
    $extractor->extract($dir . '/does-not-exist.docx', 'does-not-exist.docx', $dir);
    check(false, 'Missing file is rejected with a controlled unavailable-file error');
} catch (RuntimeException $error) {
    check($error->getMessage() === 'FILE_UNAVAILABLE', 'Missing file is rejected with a controlled unavailable-file error');
}
if (PHP_OS_FAMILY !== 'Windows' && function_exists('posix_geteuid') && posix_geteuid() !== 0) {
    $unreadable = $dir . '/fishing.docx';
    chmod($unreadable, 0000);
    clearstatcache(true, $unreadable);
    try {
        $extractor->extract($unreadable, 'fishing.docx', $dir);
        check(false, 'File without read permissions is rejected without aborting PHP');
    } catch (RuntimeException $error) {
        check($error->getMessage() === 'FILE_UNAVAILABLE', 'File without read permissions is rejected without aborting PHP');
    } finally {
        chmod($unreadable, 0600);
        clearstatcache(true, $unreadable);
    }
} else {
    echo "SKIP: Unix read-permission test requires a non-root Unix process.\n";
}
echo "$testCount total extraction checks passed.\n";

<?php
header('Content-Type: text/plain; charset=utf-8');

echo "PHP running as: ";
echo function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : '?';
echo "\n\n";

$dir = __DIR__ . '/../uploads/repository/';
$testFile = null;
foreach ((array)@scandir($dir) as $f) {
    if (preg_match('/\.(docx?|xlsx?|pptx?)$/i', $f)) {
        $testFile = $dir . $f;
        break;
    }
}
if (!$testFile) { echo "No office file in $dir\n"; exit; }

echo "Test file: $testFile\n";
echo "  exists: " . (is_file($testFile) ? 'yes' : 'no') . "\n";
echo "  readable: " . (is_readable($testFile) ? 'yes' : 'no') . "\n\n";

$cacheDir = __DIR__ . '/../uploads/pdf_cache/';
echo "Cache dir: $cacheDir\n";
echo "  is_dir: " . (is_dir($cacheDir) ? 'yes' : 'no') . "\n";
echo "  is_writable: " . (is_writable($cacheDir) ? 'yes' : 'no') . "\n";
$st = @stat($cacheDir);
if ($st) {
    echo "  owner uid=" . $st['uid'] . " mode=" . decoct($st['mode'] & 0777) . "\n";
}
echo "\n";

$bin = '/usr/bin/libreoffice';
if (!is_file($bin)) $bin = '/usr/bin/soffice';
if (!is_file($bin)) $bin = 'libreoffice';
echo "Binary: $bin\n";
echo "  exists: " . (is_file($bin) ? 'yes' : 'no') . "\n";
echo "  executable: " . (is_executable($bin) ? 'yes' : 'no') . "\n\n";

$workDir = '/tmp/lo_dbg_' . uniqid();
@mkdir($workDir, 0777, true);
$profile = $workDir . '/profile';
@mkdir($profile, 0777, true);

$cmd = 'export HOME=' . escapeshellarg($workDir)
     . '; ' . escapeshellarg($bin)
     . ' --headless --norestore --nologo --nofirststartwizard'
     . ' --convert-to pdf'
     . ' --outdir ' . escapeshellarg($cacheDir)
     . ' -env:UserInstallation=' . escapeshellarg('file://' . $profile . '/lo_config')
     . ' ' . escapeshellarg($testFile)
     . ' 2>&1';

echo "CMD:\n$cmd\n\n";

$out = []; $rc = 0;
exec($cmd, $out, $rc);
echo "RC: $rc\n";
echo "Output:\n" . implode("\n", $out) . "\n\n";

$pdfs = glob($cacheDir . '*.pdf');
echo "PDFs in cache: " . count($pdfs) . "\n";
foreach ($pdfs as $p) {
    echo "  - " . basename($p) . " (" . filesize($p) . " bytes)\n";
}

@exec('rm -rf ' . escapeshellarg($workDir));
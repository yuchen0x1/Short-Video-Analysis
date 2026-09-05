<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PhpParser\Error;
use PhpParser\ParserFactory;

$root = dirname(__DIR__);
if (!chdir($root)) {
    fwrite(STDERR, '无法切换到项目根目录: ' . $root . PHP_EOL);
    exit(1);
}

$directories = ['app', 'config', 'public'];
$files = [];

foreach ($directories as $directory) {
    $path = $root . DIRECTORY_SEPARATOR . $directory;
    if (!is_dir($path)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $files[] = $file->getPathname();
        }
    }
}

sort($files);
$parser = (new ParserFactory())->createForNewestSupportedVersion();
$failed = false;
$rootReal = realpath($root);

foreach ($files as $file) {
    $realFile = realpath($file);
    if ($rootReal === false || $realFile === false || strncmp($realFile, $rootReal, strlen($rootReal)) !== 0) {
        continue;
    }

    $source = file_get_contents($realFile);
    if ($source === false) {
        fwrite(STDERR, '无法读取 PHP 文件: ' . $file . PHP_EOL);
        $failed = true;
        continue;
    }

    try {
        $parser->parse($source);
        echo $file . ': No syntax errors detected' . PHP_EOL;
    } catch (Error $error) {
        echo $file . ': ' . $error->getMessage() . PHP_EOL;
        $failed = true;
    }
}

exit($failed ? 1 : 0);

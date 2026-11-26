<?php

$appDir = dirname(__DIR__);

// The fake app's prod contexts boot from scripts compiled with the resources of the day; a resource added since
// would be unbound, so the scripts are compiled again on each run.
$buildDir = $appDir . '/tests/Fake/app/var/build';
if (is_dir($buildDir)) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($buildDir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        if ($file->isDir() && ! $file->isLink()) {
            rmdir($file->getPathname());

            continue;
        }

        unlink($file->getPathname());
    }
}

passthru(sprintf('cd %s/tests/Fake/app', $appDir)
    . ' && composer config scripts.bin "echo installing"'
    . ' && composer update --no-dev'
);

echo 'setup done.' . PHP_EOL;

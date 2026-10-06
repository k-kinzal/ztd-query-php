<?php

/**
 * Checks that the lowering rules of a database package claim every production of every shipped grammar release.
 *
 * Usage: php production-ledger.php <database package directory> [--verbose]
 *
 * The productions of each release are listed in `resources/productions/<release>.php`. A production is claimed
 * when its signature (for example `where_opt: WHERE expr`) appears as a single-quoted string literal in a file
 * under `src/Lowering`, which is how rules dispatch on productions. A claimed signature that no release has is
 * stale. The exit code is 0 only when nothing is unclaimed and nothing is stale.
 *
 * The ledger establishes that a rule exists for each production, not that the rule is correct; the fuzz targets,
 * the publication checks and the unit tests test that.
 */

declare(strict_types=1);

$package = $argv[1] ?? null;
if ($package === null || !is_dir($package . '/src/Lowering') || !is_dir($package . '/resources/productions')) {
    fwrite(STDERR, "Usage: php production-ledger.php <database package directory> [--verbose]\n");
    exit(2);
}
$claimed = [];
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($package . '/src/Lowering', FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
        continue;
    }
    $source = file_get_contents($file->getPathname());
    if ($source === false) {
        fwrite(STDERR, 'Cannot read ' . $file->getPathname() . "\n");
        exit(2);
    }
    foreach (token_get_all($source) as $token) {
        if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING && $token[1][0] === "'") {
            $literal = stripcslashes(substr($token[1], 1, -1));
            if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*:( .*)?\z/', $literal) === 1) {
                $claimed[$literal] = true;
            }
        }
    }
}
$productions = [];
foreach (glob($package . '/resources/productions/*.php') ?: [] as $path) {
    $release = require $path;
    if (!is_array($release)) {
        fwrite(STDERR, 'Invalid production list ' . $path . "\n");
        exit(2);
    }
    foreach ($release as $signatures) {
        foreach ((array) $signatures as $signature) {
            $productions[(string) $signature][] = basename($path, '.php');
        }
    }
}
$unclaimed = array_diff_key($productions, $claimed);
$stale = array_diff_key($claimed, $productions);
printf("%d productions, %d claimed, %d unclaimed, %d stale\n", count($productions), count($productions) - count($unclaimed), count($unclaimed), count($stale));
if (in_array('--verbose', $argv, true) || $unclaimed !== [] || $stale !== []) {
    foreach ($unclaimed as $signature => $releases) {
        echo 'unclaimed: ', $signature, ' (', implode(', ', $releases), ")\n";
    }
    foreach (array_keys($stale) as $signature) {
        echo 'stale: ', $signature, "\n";
    }
}
exit($unclaimed === [] && $stale === [] ? 0 : 1);

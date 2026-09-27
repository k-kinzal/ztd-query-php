<?php

declare(strict_types=1);

use Tests\Fake\Oracle\StateAgreement;
use Tests\Fake\Oracle\StatePrograms;

require dirname(__DIR__) . '/vendor/autoload.php';

$count = $argv[1] ?? '100';
$start = $argv[2] ?? '0';
if (!ctype_digit($count) || !ctype_digit($start) || (int) $count < 1 || (int) $count > 100000 || (int) $start > 1000000000) {
    fwrite(STDERR, "Usage: php fuzz/semantic.php [seed-count:1..100000] [first-seed:0..1000000000]\n");
    exit(2);
}
$tested = 0;
for ($seed = (int) $start; $seed < (int) $start + (int) $count; $seed++) {
    $operations = StatePrograms::operations($seed);
    foreach ([-1, 0, 1] as $input) {
        if (!StateAgreement::agrees($operations, $input)) {
            $minimal = StateAgreement::shrink($operations, $input);
            $directory = dirname(__DIR__) . '/build/semantic-failures';
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
            $file = $directory . '/' . $seed . '-' . $input . '.php';
            file_put_contents($file, StatePrograms::source($minimal));
            fwrite(STDERR, 'Counterexample: seed=' . $seed . ' input=' . $input . ' fixture=' . $file . "\n");
            exit(1);
        }
        $tested++;
    }
}
echo json_encode(['seeds' => (int) $count, 'firstSeed' => (int) $start, 'executions' => $tested, 'mismatches' => 0], JSON_THROW_ON_ERROR), "\n";

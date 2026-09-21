<?php

declare(strict_types=1);

namespace Fuzz;

/**
 * Makes PHP-Fuzzer findings fail a Composer or CI job even when its process exits zero.
 */
final class Runner
{
    /**
     * Runs unrestricted byte-plan generation with reproducible initial decisions.
     */
    public function run(string $dialect, string $release, int $runs): int
    {
        $name = $dialect . '-' . $release;
        $corpus = __DIR__ . '/corpus/' . $name;
        $artifacts = __DIR__ . '/../build/fuzz/' . $name;
        foreach ([$corpus, $artifacts] as $directory) {
            if (!is_dir($directory) && !mkdir($directory, 0777, true)) {
                return 1;
            }
        }
        for ($index = 0; $index < 64; ++$index) {
            $seed = $corpus . '/initial-' . $index;
            if (!is_file($seed)) {
                file_put_contents($seed, str_repeat(hash('sha512', $name . ':' . $index, true), 4));
            }
        }
        $regressions = glob(__DIR__ . '/seeds/' . $name . '/*.hex');
        if ($regressions === false) {
            return 1;
        }
        foreach ($regressions as $regression) {
            $hex = file_get_contents($regression);
            $input = $hex === false ? false : hex2bin(trim($hex));
            if ($input === false) {
                return 1;
            }
            file_put_contents($corpus . '/' . basename($regression), $input);
        }
        $log = $artifacts . '/fuzzer.log';
        $command = [PHP_BINARY, '-d', 'memory_limit=2G', __DIR__ . '/../vendor/bin/php-fuzzer', 'fuzz', __DIR__ . '/fuzz_' . $dialect . '_semantics.php', $corpus, '--max-runs=' . $runs, '--timeout=10'];
        echo 'Checking ' . $name . ' (' . $runs . " mutations plus corpus replay)\n";
        $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['file', $log, 'w'], 2 => ['file', $artifacts . '/stderr.log', 'w']], $pipes, $artifacts);
        if (!is_resource($process)) {
            return 1;
        }
        $status = proc_close($process);
        $output = file_get_contents($log);
        if ($output === false) {
            return 1;
        }
        $errors = file_get_contents($artifacts . '/stderr.log');
        $output .= $errors === false ? '' : $errors;
        echo $output;
        return $this->status($status, $output);
    }

    /**
     * Converts findings, corpus failures, and instrumentation errors into a failed run.
     */
    public function status(int $exitCode, string $output): int
    {
        return $exitCode !== 0 || str_contains($output, 'CRASH') || str_contains($output, 'ERROR') ? 1 : 0;
    }
}

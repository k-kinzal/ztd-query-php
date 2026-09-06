<?php

declare(strict_types=1);

namespace Tests\Fixtures\SqlFaker;

use RuntimeException;

/**
 * Executes the real SQLite target in an isolated process and report directory.
 */
final class FuzzProcess
{
    /**
     * @param list<string> $arguments
     * @return array{exitCode: int, output: string}
     * @throws RuntimeException When the child cannot be launched or read
     */
    public static function run(array $arguments, string $directory): array
    {
        $environment = getenv();
        $environment['XDEBUG_MODE'] = 'off';
        $environment['FUZZ_CORPUS_DIRECTORY'] = $directory . '/corpus';
        $environment['FUZZ_COVERAGE_DIRECTORY'] = $directory . '/coverage';
        $environment['FUZZ_REPORT_DIRECTORY'] = $directory . '/reports';
        $capture = fopen($directory . '/process.log', 'w+');
        if ($capture === false) {
            throw new RuntimeException('Cannot open child output capture.');
        }
        $process = proc_open(
            [PHP_BINARY, 'vendor/bin/php-fuzzer', ...$arguments],
            [0 => ['file', '/dev/null', 'r'], 1 => $capture, 2 => $capture],
            $pipes,
            dirname(__DIR__, 2),
            $environment
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot launch the fuzz target.');
        }
        $exitCode = proc_close($process);
        rewind($capture);
        $output = stream_get_contents($capture);
        fclose($capture);
        if ($output === false) {
            throw new RuntimeException('Cannot read the fuzz target output.');
        }
        return ['exitCode' => $exitCode, 'output' => $output];
    }

    /**
     * Removes isolated child-process artifacts after all writers have exited.
     */
    public static function remove(string $directory): void
    {
        $files = glob($directory . '/*');
        foreach ($files === false ? [] : $files as $file) {
            if (is_dir($file)) {
                self::remove($file);
            } else {
                unlink($file);
            }
        }
        rmdir($directory);
    }
}

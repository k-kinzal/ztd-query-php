<?php

declare(strict_types=1);

namespace Conformance;

/**
 * Checks a corpus and writes the verdicts.
 */
final class Runner
{
    /**
     * @param Checker $checker Checks one file
     * @param resource $output Where to write
     */
    public function __construct(
        private readonly Checker $checker,
        private $output,
    ) {
    }

    /**
     * Checks every file and writes a line per file and a summary.
     *
     * @param list<string> $files The grammar files
     *
     * @return bool True when no file counts as a failure
     */
    public function run(array $files): bool
    {
        $counts = [];
        $failed = false;
        foreach ($files as $path) {
            $result = $this->checker->check($path);
            $counts[$result->verdict->value] = ($counts[$result->verdict->value] ?? 0) + 1;
            $failed = $failed || $result->verdict->fails();
            if ($result->verdict->fails() || $result->detail !== '') {
                fwrite($this->output, sprintf("%s %s: %s%s\n", $result->verdict->fails() ? 'FAIL' : 'ok  ', $path, $result->verdict->value, $result->detail === '' ? '' : " ({$result->detail})"));
            }
        }
        fwrite($this->output, sprintf("\n%d files\n", count($files)));
        foreach ($counts as $verdict => $count) {
            fwrite($this->output, sprintf("  %5d %s\n", $count, $verdict));
        }

        return !$failed;
    }
}

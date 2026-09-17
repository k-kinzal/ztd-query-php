<?php

declare(strict_types=1);

namespace Conformance;

/**
 * Checks a corpus and writes the verdicts.
 */
final class Runner
{
    /**
     * @param Checker $checker Checks one file under one set of defines
     * @param Corpus $corpus Knows the defines of each file
     * @param resource $output Where to write
     */
    public function __construct(
        private readonly Checker $checker,
        private readonly Corpus $corpus,
        private $output,
    ) {
    }

    /**
     * Checks every file under each of its define sets and writes a line per check and a summary.
     *
     * @param list<string> $files The grammar files
     *
     * @return bool True when no check counts as a failure
     */
    public function run(array $files): bool
    {
        $counts = [];
        $failed = false;
        $checks = 0;
        foreach ($files as $path) {
            foreach ($this->corpus->defineSets($path) as $defines) {
                $checks++;
                $result = $this->checker->check($path, $defines);
                $counts[$result->verdict->value] = ($counts[$result->verdict->value] ?? 0) + 1;
                $failed = $failed || $result->verdict->fails();
                if ($result->verdict->fails() || $result->detail !== '') {
                    fwrite($this->output, sprintf("%s %s: %s%s\n", $result->verdict->fails() ? 'FAIL' : 'ok  ', $result->label(), $result->verdict->value, $result->detail === '' ? '' : " ({$result->detail})"));
                }
            }
        }
        fwrite($this->output, sprintf("\n%d checks on %d files\n", $checks, count($files)));
        foreach ($counts as $verdict => $count) {
            fwrite($this->output, sprintf("  %5d %s\n", $count, $verdict));
        }

        return !$failed;
    }
}

<?php

declare(strict_types=1);

namespace Conformance;

use RuntimeException;

/**
 * Runs GNU Bison on a grammar file and collects its XML report.
 *
 * Reports of large grammars run to tens of megabytes, so they stay on disk
 * and are compared by digest, line by line, without being held in memory.
 */
final class Reference
{
    /**
     * @param string $bison Path of the bison executable
     */
    public function __construct(private readonly string $bison)
    {
    }

    /**
     * Answers Bison's version line.
     *
     * @return string The first line of `bison --version`
     *
     * @throws RuntimeException When bison cannot be run
     */
    public function version(): string
    {
        [$stdout] = $this->run([$this->bison, '--version']);
        $line = strtok($stdout, "\n");
        if ($line === false) {
            throw new RuntimeException("Cannot run {$this->bison}");
        }

        return $line;
    }

    /**
     * Reports what Bison makes of a grammar file, leaving the XML report next to it.
     *
     * Bison is run inside the file's directory and given the bare file
     * name, so that two copies of a grammar under the same name in two
     * directories are read under the same name: Bison orders symbols by
     * location, and a location includes the file name.
     *
     * @param string $directory The directory holding the file
     * @param string $name The file name inside it
     *
     * @return Reading The report, or why there is none
     */
    public function report(string $directory, string $name): Reading
    {
        $xml = "{$directory}/report.xml";
        [, $stderr, $status] = $this->run([$this->bison, '--xml=report.xml', '-o', 'parser.c', $name], $directory);
        if (!is_file($xml)) {
            return new Reading(null, null, $stderr, $status);
        }

        return new Reading($xml, $this->digest($xml), $stderr, $status);
    }

    /**
     * Digests a report, skipping the line that names the file.
     *
     * @param string $xml Path of the report
     *
     * @return string A hash of every other line
     */
    public function digest(string $xml): string
    {
        $context = hash_init('sha256');
        foreach ($this->lines($xml) as $line) {
            hash_update($context, $line . "\n");
        }

        return hash_final($context);
    }

    /**
     * Reads the lines of a report that say what the grammar means.
     *
     * The line naming the file is left out. Symbol numbers are left out and
     * the symbols of each table are sorted by name: Bison numbers symbols by
     * location and breaks ties by whatever order its hash table yields, so
     * a multi-start grammar, whose switching tokens share the location of
     * their start symbols, numbers them one way or the other depending on
     * the layout of the file. The automaton does not depend on them.
     *
     * @param string $xml Path of the report
     *
     * @return iterable<int, string> The lines without their line breaks
     *
     * @throws RuntimeException When the report cannot be read
     */
    public function lines(string $xml): iterable
    {
        $handle = fopen($xml, 'r');
        if ($handle === false) {
            throw new RuntimeException("Cannot read {$xml}");
        }
        try {
            $symbols = null;
            while (($line = fgets($handle)) !== false) {
                $line = rtrim($line, "\n");
                if (preg_match('~^\s*<filename>.*</filename>$~', $line) === 1) {
                    continue;
                }
                if (preg_match('~^\s*<(terminal|nonterminal) ~', $line) === 1) {
                    $symbols[] = preg_replace('~ symbol-number="[0-9]+"~', '', $line) ?? $line;
                    continue;
                }
                if ($symbols !== null) {
                    sort($symbols, SORT_STRING);
                    yield from $symbols;
                    $symbols = null;
                }
                yield $line;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Runs a command and collects its output.
     *
     * @param list<string> $command The command and its arguments
     * @param string|null $directory Where to run it, or null for the current directory
     *
     * @return array{string, string, int} Standard output, standard error and the exit status
     *
     * @throws RuntimeException When the process cannot be started
     */
    public function run(array $command, ?string $directory = null): array
    {
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $directory);
        if ($process === false) {
            throw new RuntimeException('Cannot start ' . implode(' ', $command));
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);

        return [$stdout === false ? '' : $stdout, $stderr === false ? '' : $stderr, $status];
    }

    /**
     * Makes a fresh temporary directory.
     *
     * @return string Its path
     *
     * @throws RuntimeException When none can be made
     */
    public function temporaryDirectory(): string
    {
        $path = sys_get_temp_dir() . '/bison-conformance-' . bin2hex(random_bytes(6));
        if (!mkdir($path, 0700) && !is_dir($path)) {
            throw new RuntimeException("Cannot create {$path}");
        }

        return $path;
    }

    /**
     * Removes a temporary directory, its files and its subdirectories.
     *
     * @param string $directory The directory
     */
    public function remove(string $directory): void
    {
        $entries = glob("{$directory}/*");
        foreach ($entries === false ? [] : $entries as $entry) {
            if (is_dir($entry)) {
                $this->remove($entry);
            } else {
                unlink($entry);
            }
        }
        rmdir($directory);
    }
}

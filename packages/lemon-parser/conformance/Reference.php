<?php

declare(strict_types=1);

namespace Conformance;

use RuntimeException;

/**
 * Runs Lemon on a grammar file and collects what it says about the grammar.
 *
 * The report joins three things Lemon writes: the reprint of the grammar
 * with its symbol numbering (`-g`), the header with the token numbers
 * (`-d`), and the parser report with every state, action and precedence
 * (`.out`). Any change to what the grammar means changes one of them.
 */
final class Reference
{
    /**
     * @param string $lemon Path of the lemon executable
     */
    public function __construct(private readonly string $lemon)
    {
    }

    /**
     * Answers Lemon's version line.
     *
     * @return string The output of `lemon -x`
     */
    public function version(): string
    {
        [$stdout] = $this->run([$this->lemon, '-x']);

        return trim($stdout);
    }

    /**
     * Reports what Lemon makes of a grammar file, running inside its directory on its bare name.
     *
     * @param string $directory The directory holding the file and receiving Lemon's outputs
     * @param string $name The file name inside it, ending in `.y`
     * @param list<string> $defines Names for `-D`
     *
     * @return Reading The report, or why there is none
     */
    public function report(string $directory, string $name, array $defines): Reading
    {
        $options = array_map(static fn (string $define): string => "-D{$define}", $defines);
        [$preprocessed] = $this->run([$this->lemon, ...$options, '-E', $name], $directory);
        [$stdout, $stderr, $status] = $this->run([$this->lemon, ...$options, '-T/dev/null', $name], $directory);
        $base = "{$directory}/" . pathinfo($name, PATHINFO_FILENAME);
        if (!is_file("{$base}.out")) {
            return new Reading(null, $preprocessed, $stdout . $stderr, $status);
        }
        [$reprint] = $this->run([$this->lemon, ...$options, '-g', $name], $directory);
        $report = preg_replace('~^// Reprint of input file "[^"]*"\.\n~', '', $reprint) . "\n" . file_get_contents("{$base}.h") . "\n" . file_get_contents("{$base}.out");

        return new Reading($report, $preprocessed, $stdout . $stderr, $status);
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
        $path = sys_get_temp_dir() . '/lemon-conformance-' . bin2hex(random_bytes(6));
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

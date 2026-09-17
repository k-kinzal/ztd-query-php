<?php

declare(strict_types=1);

namespace Conformance;

use BisonParser\Parser;
use BisonParser\Printer\Printer;
use BisonParser\SyntaxException;

/**
 * Compares what this package reads from a grammar file with what GNU Bison reads.
 *
 * A file Bison accepts must parse here, and printing the tree must give a
 * file from which Bison produces the same report, rules, symbols and
 * automaton alike; both are read under the same file name, since Bison
 * orders symbols by location and a location names its file. A file
 * Bison's scanner or parser refuses must raise a SyntaxException here. A
 * file Bison refuses for its meaning is only read.
 */
final class Checker
{
    /**
     * @param Reference $reference Runs Bison
     * @param Parser $parser Reads grammar files
     * @param Printer $printer Writes trees back out
     * @param list<string> $knownTies SHA-256 digests of grammars Bison numbers unstably, whose reports may differ
     */
    public function __construct(
        private readonly Reference $reference,
        private readonly Parser $parser = new Parser(),
        private readonly Printer $printer = new Printer(),
        private readonly array $knownTies = [],
    ) {
    }

    /**
     * Checks one grammar file.
     *
     * @param string $path The grammar file
     *
     * @return Result The verdict
     */
    public function check(string $path): Result
    {
        $source = file_get_contents($path);
        if ($source === false) {
            return new Result($path, Verdict::Mismatch, 'cannot read the file');
        }
        $directory = $this->reference->temporaryDirectory();
        try {
            return $this->compare($path, $source, $directory);
        } finally {
            $this->reference->remove($directory);
        }
    }

    /**
     * Runs both readers and Bison on the reprint, working in a directory of its own.
     *
     * @param string $path The grammar file
     * @param string $source Its text
     * @param string $directory Where Bison's reports go
     *
     * @return Result The verdict
     */
    public function compare(string $path, string $source, string $directory): Result
    {
        $name = basename($path);
        mkdir("{$directory}/original");
        copy($path, "{$directory}/original/{$name}");
        $original = $this->reference->report("{$directory}/original", $name);
        try {
            $file = $this->parser->parse($source);
        } catch (SyntaxException $exception) {
            if ($original->accepted() || !$original->rejectsSyntax()) {
                return new Result($path, Verdict::ReadByBisonOnly, $exception->getMessage() . ($original->accepted() ? '' : ' / bison: ' . $original->firstError()));
            }

            return new Result($path, Verdict::RejectedByBoth, $exception->getMessage() . ' / bison: ' . $original->firstError());
        }
        if (!$original->accepted()) {
            return $original->rejectsSyntax()
                ? new Result($path, Verdict::ReadHereOnly, 'bison: ' . $original->firstError())
                : new Result($path, Verdict::RejectedForMeaning, 'bison: ' . $original->firstError());
        }
        mkdir("{$directory}/reprint");
        file_put_contents("{$directory}/reprint/{$name}", $this->printer->print($file));
        $reprint = $this->reference->report("{$directory}/reprint", $name);
        if (!$reprint->accepted()) {
            return new Result($path, Verdict::ReprintRejected, 'bison: ' . $reprint->firstError());
        }
        if ($reprint->digest !== $original->digest) {
            $verdict = in_array(hash('sha256', $source), $this->knownTies, true) ? Verdict::KnownTie : Verdict::Mismatch;

            return new Result($path, $verdict, $this->difference((string) $original->report, (string) $reprint->report));
        }

        return new Result($path, Verdict::Identical);
    }

    /**
     * Describes the first line where two reports differ.
     *
     * @param string $expected Path of Bison's report of the original
     * @param string $actual Path of Bison's report of the reprint
     *
     * @return string The differing lines
     */
    public function difference(string $expected, string $actual): string
    {
        $left = $this->reference->lines($expected);
        $right = $this->reference->lines($actual);
        $leftLines = is_array($left) ? $left : iterator_to_array($left, false);
        $rightLines = is_array($right) ? $right : iterator_to_array($right, false);
        $count = max(count($leftLines), count($rightLines));
        for ($index = 0; $index < $count; $index++) {
            if (($leftLines[$index] ?? null) !== ($rightLines[$index] ?? null)) {
                return sprintf('line %d: bison %s / reprint %s', $index + 1, trim($leftLines[$index] ?? '(end)'), trim($rightLines[$index] ?? '(end)'));
            }
        }

        return 'reports differ in length only';
    }
}

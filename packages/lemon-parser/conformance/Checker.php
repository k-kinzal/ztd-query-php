<?php

declare(strict_types=1);

namespace Conformance;

use LemonParser\Parser;
use LemonParser\Preprocessor\Preprocessor;
use LemonParser\Printer\Printer;
use LemonParser\SyntaxException;

/**
 * Compares what this package reads from a grammar file with what Lemon reads.
 *
 * Under each set of defines, the preprocessed text must equal `lemon -E`,
 * a file Lemon reads must parse here and print to a file from which Lemon
 * produces the same report, and a file Lemon stops reading must raise a
 * SyntaxException here. A file Lemon reads but refuses for its meaning,
 * an empty grammar for one, is only read.
 */
final class Checker
{
    /**
     * @param Reference $reference Runs Lemon
     * @param Parser $parser Reads grammar files
     * @param Preprocessor $preprocessor Settles conditional regions
     * @param Printer $printer Writes trees back out
     */
    public function __construct(
        private readonly Reference $reference,
        private readonly Parser $parser = new Parser(),
        private readonly Preprocessor $preprocessor = new Preprocessor(),
        private readonly Printer $printer = new Printer(),
    ) {
    }

    /**
     * Checks one grammar file under one set of defines.
     *
     * @param string $path The grammar file
     * @param list<string> $defines Names defined for `%ifdef`
     *
     * @return Result The verdict
     */
    public function check(string $path, array $defines): Result
    {
        $source = file_get_contents($path);
        if ($source === false) {
            return new Result($path, $defines, Verdict::Mismatch, 'cannot read the file');
        }
        $directory = $this->reference->temporaryDirectory();
        try {
            return $this->compare($path, $source, $defines, $directory);
        } finally {
            $this->reference->remove($directory);
        }
    }

    /**
     * Runs both readers and Lemon on the reprint, working in a directory of its own.
     *
     * @param string $path The grammar file
     * @param string $source Its text
     * @param list<string> $defines Names defined for `%ifdef`
     * @param string $directory Where Lemon's outputs go
     *
     * @return Result The verdict
     */
    public function compare(string $path, string $source, array $defines, string $directory): Result
    {
        $name = basename($path);
        mkdir("{$directory}/original");
        copy($path, "{$directory}/original/{$name}");
        $original = $this->reference->report("{$directory}/original", $name, $defines);
        try {
            $preprocessed = $this->preprocessor->preprocess($source, $defines);
        } catch (SyntaxException $exception) {
            $preprocessed = null;
        }
        if ($preprocessed !== null && $preprocessed . "\n" !== $original->preprocessed) {
            return new Result($path, $defines, Verdict::PreprocessMismatch, $this->difference($original->preprocessed, $preprocessed . "\n"));
        }
        try {
            $file = $this->parser->parse($source, $defines);
        } catch (SyntaxException $exception) {
            if ($original->accepted() || !$original->rejectsSyntax()) {
                return new Result($path, $defines, Verdict::ReadByLemonOnly, $exception->getMessage() . ($original->accepted() ? '' : ' / lemon: ' . $original->firstError()));
            }

            return new Result($path, $defines, Verdict::RejectedByBoth, $exception->getMessage() . ' / lemon: ' . $original->firstError());
        }
        if (!$original->accepted()) {
            return $original->rejectsSyntax()
                ? new Result($path, $defines, Verdict::ReadHereOnly, 'lemon: ' . $original->firstError())
                : new Result($path, $defines, Verdict::RejectedForMeaning, 'lemon: ' . $original->firstError());
        }
        mkdir("{$directory}/reprint");
        file_put_contents("{$directory}/reprint/{$name}", $this->printer->print($file));
        $reprint = $this->reference->report("{$directory}/reprint", $name, []);
        if (!$reprint->accepted()) {
            return new Result($path, $defines, Verdict::ReprintRejected, 'lemon: ' . $reprint->firstError());
        }
        if ($reprint->report !== $original->report) {
            return new Result($path, $defines, Verdict::Mismatch, $this->difference((string) $original->report, (string) $reprint->report));
        }

        return new Result($path, $defines, Verdict::Identical);
    }

    /**
     * Describes the first line where two texts differ.
     *
     * @param string $expected Lemon's text
     * @param string $actual This package's text
     *
     * @return string The differing lines
     */
    public function difference(string $expected, string $actual): string
    {
        $left = explode("\n", $expected);
        $right = explode("\n", $actual);
        $count = max(count($left), count($right));
        for ($index = 0; $index < $count; $index++) {
            if (($left[$index] ?? null) !== ($right[$index] ?? null)) {
                return sprintf('line %d: lemon %s / here %s', $index + 1, trim($left[$index] ?? '(end)'), trim($right[$index] ?? '(end)'));
            }
        }

        return 'texts differ in length only';
    }
}

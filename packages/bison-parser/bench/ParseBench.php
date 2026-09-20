<?php

declare(strict_types=1);

namespace Bench;

use BisonParser\Parser;
use BisonParser\Printer\Printer;
use PhpBench\Attributes as Benchmark;

/**
 * Measures reading and writing a grammar of a few hundred rules.
 */
final class ParseBench
{
    private string $source = '';

    private Parser $parser;

    private Printer $printer;

    /**
     * Builds a grammar of two hundred rules with actions, tags and precedence before measurement.
     */
    public function setUp(): void
    {
        $lines = ['%token <int> NUM 258 "number"', '%left \'+\' \'-\'', '%left \'*\' \'/\'', '%%'];
        for ($index = 0; $index < 200; $index++) {
            $lines[] = "rule{$index}[r]: rule{$index} '+' NUM { \$\$ = \$1 + \$3; } | NUM %prec '*' | %empty ;";
        }
        $this->source = implode("\n", $lines) . "\n%%\nint main() {}\n";
        $this->parser = new Parser();
        $this->printer = new Printer();
    }

    /**
     * Reads the grammar into a tree.
     */
    #[Benchmark\BeforeMethods('setUp')]
    #[Benchmark\Revs(20)]
    #[Benchmark\Iterations(5)]
    public function benchParse(): void
    {
        $this->parser->parse($this->source);
    }

    /**
     * Reads the grammar and writes it back out.
     */
    #[Benchmark\BeforeMethods('setUp')]
    #[Benchmark\Revs(20)]
    #[Benchmark\Iterations(5)]
    public function benchRoundTrip(): void
    {
        $this->printer->print($this->parser->parse($this->source));
    }
}

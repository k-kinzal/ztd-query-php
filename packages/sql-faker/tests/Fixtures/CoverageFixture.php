<?php

declare(strict_types=1);

namespace Tests\Fixtures\SqlFaker;

use Closure;
use RuntimeException;
use SqlFaker\Coverage\CoverageException;
use SqlFaker\Coverage\GrammarCoverage;
use SqlFaker\Coverage\GrammarCoverageInventory;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Terminal;

/**
 * Supplies a tiny recursive grammar with nullable and unrelated alternatives.
 */
final class CoverageFixture
{
    /**
     * Returns a grammar whose alternatives have stable original ordinals.
     */
    public static function grammar(): Grammar
    {
        return (new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [new Production([new Terminal('SELECT'), new NonTerminal('expr')]), new Production([new Terminal('DELETE')])]),
            'expr' => new ProductionRule('expr', [new Production([new Terminal('1')]),
                new Production([new NonTerminal('expr'), new Terminal('+'), new NonTerminal('expr')]), new Production([])]),
            'outside' => new ProductionRule('outside', [new Production([new Terminal('OUTSIDE')])]),
        ]))->identified();
    }

    /**
     * Attaches an independent recorder without generating any observation.
     */
    public static function coverage(?string $directory = null, string $revision = 'revision-a'): GrammarCoverage
    {
        $coverage = new GrammarCoverage($directory);
        $coverage->register(self::inventory(), $revision);
        return $coverage;
    }

    /**
     * Returns the fixed denominator used by all fixture recorders.
     */
    public static function inventory(): GrammarCoverageInventory
    {
        return new GrammarCoverageInventory(self::grammar(), 'stmt', 'test-v1');
    }

    /**
     * Records one failed path and a successful retry with separate productions.
     */
    public static function record(GrammarCoverage $coverage): void
    {
        $ids = $coverage->inventory()->denominator;
        $coverage->beginGeneration('stmt', []);
        $coverage->beginAttempt(0);
        $coverage->record(0, null, null, 'stmt', $ids[0], 'input');
        $coverage->discardAttempt('lexical failure');
        $coverage->beginAttempt(1);
        $coverage->record(0, null, null, 'stmt', $ids[1], 'input');
        $coverage->commitAttempt('sql-hash');
        $coverage->endGeneration();
    }

    /**
     * Creates an isolated disposable snapshot directory.
     */
    public static function directory(): string
    {
        $directory = sys_get_temp_dir() . '/sql-faker-coverage-' . bin2hex(random_bytes(8));
        mkdir($directory);
        return $directory;
    }

    /**
     * Removes fixture snapshots after the writer is released.
     */
    public static function remove(string $directory): void
    {
        $files = glob($directory . '/*');
        foreach ($files === false ? [] : $files as $file) {
            unlink($file);
        }
        rmdir($directory);
    }

    /**
     * Supplies a recursive grammar whose complete witnesses are valid SQLite statements.
     */
    public static function syntaxGrammar(): Grammar
    {
        return (new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal('SELECT'), new NonTerminal('expr'), new NonTerminal('tail')]),
                new Production([new Terminal('DELETE'), new Terminal('FROM'), new Terminal('missing')]),
            ]),
            'expr' => new ProductionRule('expr', [new Production([new Terminal('1')]), new Production([new NonTerminal('expr'), new Terminal('+'), new NonTerminal('expr')])]),
            'tail' => new ProductionRule('tail', [new Production([]), new Production([new Terminal('AS'), new Terminal('name')])]),
        ]))->identified();
    }

    /**
     * Realizes the small test grammar with ordinary token separation.
     *
     * @param list<string> $tokens
     */
    public static function realize(array $tokens): string
    {
        return implode(' ', $tokens);
    }

    /**
     * Exercises repeat registration after corrupt history without overwriting the file.
     *
     * @return array{failures: list<string>, contents: string|false}
     * @throws RuntimeException When a fixture snapshot was not created
     */
    public static function corruptRestore(): array
    {
        $directory = self::directory();
        $coverage = self::coverage($directory);
        self::record($coverage);
        $coverage->flush();
        $files = glob($directory . '/*.json');
        if ($files === false || $files === []) {
            throw new RuntimeException('Fixture snapshot is missing.');
        }
        unset($coverage);
        file_put_contents($files[0], '{broken');
        $coverage = new GrammarCoverage($directory);
        $failures = [];
        foreach ([0, 1] as $attempt) {
            try {
                $coverage->register(self::inventory(), 'revision-a');
            } catch (CoverageException $failure) {
                $failures[] = $failure->getMessage();
            }
        }
        $contents = file_get_contents($files[0]);
        unset($coverage);
        self::remove($directory);
        return ['failures' => $failures, 'contents' => $contents];
    }

    /**
     * Captures a domain generation failure so tests can inspect its retained trace.
     *
     * @param \SqlFaker\Grammar\Derivation\GenerationPlan<bool> $plan
     */
    public static function generationFailure(\SqlFaker\Generation\SqlGenerator $generator, \SqlFaker\Grammar\Derivation\GenerationPlan $plan): ?\SqlFaker\Grammar\GenerationException
    {
        try {
            $generator->generate($plan);
        } catch (\SqlFaker\Grammar\GenerationException $failure) {
            return $failure;
        }
        return null;
    }
    /**
     * Resolves the fixture's literal terminal names through the production candidate pipeline.
     * @param \SqlFaker\Grammar\Derivation\GenerationPlan<bool>|null $plan
     * @param Closure(int): int $choose
     * @param array<string, non-empty-list<string>> $spellings
     */
    public static function resolve(
        \SqlFaker\Grammar\Generation\Token\TerminalSequence $sequence,
        ?\SqlFaker\Grammar\Derivation\GenerationPlan $plan,
        Closure $choose,
        array $spellings = [],
    ): \SqlFaker\Grammar\Generation\Output\ResolvedOutput {
        $definitions = [];
        foreach (array_unique($sequence->names()) as $name) {
            $definitions[] = new \SqlFaker\Grammar\Generation\Lexeme\PatternLexemeGenerator(
                $name,
                '~\\A.*\\z~Ds',
                $spellings[$name] ?? [$name],
                'fixture',
                'fixture-literal'
            );
        }
        return (new \SqlFaker\Grammar\Generation\Output\ReverseLexemeGenerator(
            new \SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator(...$definitions),
            new \SqlFaker\Grammar\Generation\Output\CandidateResolver(new \SqlFaker\Grammar\Generation\Spacing\CombinedSpacingRule()),
            'fixture',
        ))->generate($sequence, $plan, $choose);
    }
    /**
     * Supplies a resolved literal response for lexical contract mocks.
     */
    public static function output(string $text): \SqlFaker\Grammar\Generation\Output\ResolvedOutput
    {
        return self::resolve(\SqlFaker\Grammar\Generation\Token\TerminalSequence::fromNames([$text]), null, static fn (int $count): int => 0);
    }
}

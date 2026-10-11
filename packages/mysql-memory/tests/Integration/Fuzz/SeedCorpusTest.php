<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Comparison;
use Fuzz\Target\Seed;
use Fuzz\Target\SeedCorpus;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversNothing]
#[Medium]
final class SeedCorpusTest extends TestCase
{
    #[DataProvider('providerDiagnostics')]
    public function testProfileInputsPreservesGrammarDiagnostics(string $file, string $target): void
    {
        $corpus = new SeedCorpus('mysql-8.4.7', diagnostics: true);
        $directory = dirname(__DIR__, 3) . '/vendor/k-kinzal/sql-faker/seeds/mysql/mysql-8.4.7/diagnostics';
        $seed = $corpus->profileInputs($directory . '/' . $file, diagnostics: true)->current();
        $labels = array_map(static fn (string $id): string => explode(':', $id)[1], $seed->parsed);

        self::assertContains($target, $labels);
        self::assertFalse($seed->structuralRewrites);
        self::assertSame('diagnostics/' . $file, $seed->file);
        self::assertSame($seed->sql, $seed->executionSql());
        self::assertCount(3114, $corpus->denominator());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerDiagnostics(): iterable
    {
        yield 'return outside a function' => ['ev_sql_stmt_inner-1.txt', 'ev_sql_stmt_inner#1'];
        yield 'unsigned width' => ['field_length-1.txt', 'field_length#1'];
        yield 'decimal width' => ['field_length-2.txt', 'field_length#2'];
        yield 'decimal connection' => ['real_ulong_num-4.txt', 'real_ulong_num#4'];
        yield 'decimal spatial id' => ['real_ulonglong_num-4.txt', 'real_ulonglong_num#4'];
        yield 'floating spatial id' => ['dec_num-1.txt', 'dec_num#1'];
        yield 'names expression' => ['option_value_no_option_type-4.txt', 'option_value_no_option_type#4'];
    }

    #[DataProvider('providerModes')]
    public function testProfileInputsUsesTheSameModeForGenerationParsingAndExecution(string $mode, string $file, string $target): void
    {
        $corpus = new SeedCorpus('mysql-8.4.7', $mode);
        $directory = dirname(__DIR__, 3) . '/vendor/k-kinzal/sql-faker/seeds/mysql/mysql-8.4.7/modes/' . $mode;
        $seed = $corpus->profileInputs($directory . '/' . $file, $mode)->current();
        $labels = array_map(static fn (string $id): string => explode(':', $id)[1], $seed->parsed);

        self::assertContains($target, $labels);
        self::assertSame($mode, $seed->mode);
        self::assertStringStartsWith('modes/' . $mode . '/', $seed->file);
        self::assertSame("SET SESSION sql_mode = CONCAT_WS(',', @@SESSION.sql_mode, '$mode'); " . $seed->sql, $seed->executionSql());
        self::assertCount(3114, $corpus->denominator());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerModes(): iterable
    {
        yield 'infix NOT' => ['HIGH_NOT_PRECEDENCE', 'not-1.txt', 'not#1'];
        yield 'unary NOT' => ['HIGH_NOT_PRECEDENCE', 'not2-1.txt', 'not2#1'];
        yield 'concatenation' => ['PIPES_AS_CONCAT', 'simple_expr-12.txt', 'simple_expr#12'];
    }

    public function testProfileInputsRejectsUnknownModesBeforeReadingAnySeed(): void
    {
        $corpus = new SeedCorpus('mysql-8.4.7');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported corpus SQL mode: UNKNOWN_MODE');
        $corpus->profileInputs('/unused', 'UNKNOWN_MODE')->current();
    }

    public function testRecordDoesNotVerifyAlternativesRemovedByGenerationRewrites(): void
    {
        $corpus = new SeedCorpus('mysql-8.4.7');
        $inputs = $corpus->inputs(dirname(__DIR__, 3) . '/vendor/k-kinzal/sql-faker/seeds/mysql/mysql-8.4.7');
        $original = $inputs->current();
        $reached = array_slice($original->reached, 0, 2);
        $retained = [$reached[0]];
        $seed = new Seed($original->file, $original->input, $original->sql, $reached, $reached, $retained);
        $corpus->record($seed, new Comparison(false));
        $summary = $corpus->summary();

        self::assertSame(2, $summary['matchedReached']);
        self::assertSame(2, $summary['matchedEmitted']);
        self::assertSame(1, $summary['matched']);
        self::assertContains($reached[1], $summary['unverified']);
        self::assertNotContains($reached[0], $summary['unverified']);
    }

    public function testInputsPreservesTheCanonicalSeedContract(): void
    {
        $corpus = new SeedCorpus('mysql-8.4.7');
        $inputs = $corpus->inputs(dirname(__DIR__, 3) . '/vendor/k-kinzal/sql-faker/seeds/mysql/mysql-8.4.7');
        $seed = $inputs->current();

        self::assertSame('IDENT_sys-1.txt', $seed->file);
        self::assertSame('05000000000003000000000001000000000000', bin2hex($seed->input));
        self::assertSame("ALTER LOGFILE GROUP `name` ADD UNDOFILE 'text'", $seed->sql);
        self::assertSame($seed->sql, $seed->executionSql());
        self::assertSame('', $seed->mode);
        self::assertCount(3114, $corpus->denominator());
        self::assertNotEmpty($seed->reached);
        self::assertNotEmpty($seed->emitted);
        self::assertNotEmpty($seed->parsed);
    }

    public function testRecordDoesNotCountVolatileOrDifferentStatementsAsVerifiedCoverage(): void
    {
        $corpus = new SeedCorpus('mysql-8.4.7');
        $inputs = $corpus->inputs(dirname(__DIR__, 3) . '/vendor/k-kinzal/sql-faker/seeds/mysql/mysql-8.4.7');
        $seed = $inputs->current();
        self::assertSame('volatile', $corpus->record($seed, new Comparison(true)));
        self::assertSame('different', $corpus->record($seed, new Comparison(false, 'mismatch')));
        self::assertSame(0, $corpus->summary()['matched']);
        self::assertSame('matched', $corpus->record($seed, new Comparison(false)));
        $summary = $corpus->summary();

        self::assertGreaterThan(0, $summary['matched']);
        self::assertSame(['matched' => 1, 'different' => 1, 'volatile' => 1], $summary['counts']);
        self::assertNotEmpty($summary['unreached']);
        self::assertNotEmpty($summary['unverified']);
    }
}

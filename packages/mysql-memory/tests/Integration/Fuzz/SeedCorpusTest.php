<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Comparison;
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
    public function testProfileInputsPreservesGrammarDiagnostics(string $file, string $sql): void
    {
        $corpus = new SeedCorpus('mysql-8.4.7', diagnostics: true);
        $directory = dirname(__DIR__, 3) . '/vendor/k-kinzal/sql-faker/seeds/mysql/mysql-8.4.7/diagnostics';
        $seed = $corpus->profileInputs($directory . '/' . $file, diagnostics: true)->current();

        self::assertSame($sql, $seed->sql);
        self::assertFalse($seed->structuralRewrites);
        self::assertSame('diagnostics/' . $file, $seed->file);
        self::assertSame($seed->sql, $seed->executionSql());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerDiagnostics(): iterable
    {
        yield 'return outside a function' => ['ev_sql_stmt_inner-1.txt', 'ALTER EVENT _sqlfaker_identifier DO RETURN USER ( )'];
        yield 'unsigned width' => ['field_length-1.txt', 'CREATE DEFINER = CURRENT_USER FUNCTION _sqlfaker_identifier ( ) RETURNS BIT ( 18446744073709551615 ) AS $$text$$'];
        yield 'decimal width' => ['field_length-2.txt', 'CREATE DEFINER = CURRENT_USER FUNCTION _sqlfaker_identifier ( ) RETURNS BIT ( 18446744073709551616 ) AS $$text$$'];
        yield 'decimal connection' => ['real_ulong_num-4.txt', 'DESC ANALYZE FOR CONNECTION 18446744073709551616'];
        yield 'decimal spatial id' => ['real_ulonglong_num-4.txt', 'CREATE OR REPLACE SPATIAL REFERENCE SYSTEM 18446744073709551616'];
        yield 'floating spatial id' => ['dec_num-1.txt', 'CREATE OR REPLACE SPATIAL REFERENCE SYSTEM 1e2'];
        yield 'names expression' => ['option_value_no_option_type-4.txt', 'SET NAMES = USER ( )'];
    }

    #[DataProvider('providerModes')]
    public function testProfileInputsUsesTheSuppliedModeForGenerationAndExecution(string $mode, string $file, string $sql): void
    {
        $corpus = new SeedCorpus('mysql-8.4.7', $mode);
        $directory = dirname(__DIR__, 3) . '/vendor/k-kinzal/sql-faker/seeds/mysql/mysql-8.4.7/modes/' . $mode;
        $seed = $corpus->profileInputs($directory . '/' . $file, $mode)->current();

        self::assertSame($sql, $seed->sql);
        self::assertSame($mode, $seed->mode);
        self::assertStringStartsWith('modes/' . $mode . '/', $seed->file);
        self::assertSame("SET SESSION sql_mode = CONCAT_WS(',', @@SESSION.sql_mode, '$mode'); " . $seed->sql, $seed->executionSql());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerModes(): iterable
    {
        yield 'infix NOT' => ['HIGH_NOT_PRECEDENCE', 'not-1.txt', 'CREATE DATABASE IF NOT EXISTS _sqlfaker_identifier'];
        yield 'unary NOT' => ['HIGH_NOT_PRECEDENCE', 'not2-1.txt', 'SELECT * INTO DUMPFILE \'text\' HAVING ( ( ( ( ( ! TRUE ) ) ) ) = ( ( ( USER ( ) ) ) MEMBER ( ( NOT ( USER ( ) ) ) ) ) )'];
        yield 'concatenation' => ['PIPES_AS_CONCAT', 'simple_expr-12.txt', 'SELECT * INTO DUMPFILE \'text\' HAVING ( ( ( ( ( \'text\' || \'text\' ) ) ) ) = ( ( ( USER ( ) ) ) MEMBER ( ( ( USER ( ) ) || ( USER ( ) ) ) ) ) )'];
    }

    public function testProfileInputsRejectsUnknownModesBeforeReadingAnySeed(): void
    {
        $corpus = new SeedCorpus('mysql-8.4.7');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported corpus SQL mode: UNKNOWN_MODE');
        $corpus->profileInputs('/unused', 'UNKNOWN_MODE')->current();
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
    }

    public function testRecordKeepsExecutionOutcomesSeparate(): void
    {
        $corpus = new SeedCorpus('mysql-8.4.7');
        self::assertSame(0, $corpus->summary()['inputs']);
        self::assertSame('volatile', $corpus->record(new Comparison(true)));
        self::assertSame('different', $corpus->record(new Comparison(false, 'mismatch')));
        self::assertSame('matched', $corpus->record(new Comparison(false)));

        self::assertSame([
            'grammar' => 'mysql-8.4.7',
            'root' => 'simple_statement_or_begin',
            'counts' => ['matched' => 1, 'different' => 1, 'volatile' => 1],
            'inputs' => 3,
        ], $corpus->summary());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\ModernWarnings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;

#[CoversClass(ModernWarnings::class)]
#[Small]
final class ModernWarningsTest extends TestCase
{
    public function testRecordedPreservesReplicationKeywordWarnings(): void
    {
        $session = (new Instance('8.0.44'))->connect();
        $reply = $session->run('CHANGE MASTER TO MASTER_LOG_POS=0xb')[0];

        self::assertInstanceOf(SqlError::class, $reply);
        self::assertSame(1064, $reply->getCode());
        self::assertSame([1287, 1287, 1064], array_column($session->diagnostics->conditions, 1));
        self::assertStringContainsString('MASTER_LOG_POS', $session->diagnostics->conditions[1][2]);
    }

    /**
     * @return iterable<string, array{string, int, list<int>}>
     */
    public static function providerFailures(): iterable
    {
        yield 'routine database before zero code and syntax' => ['CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR 0; SELECT +; END', 1046, [1046]];
        yield 'routine database before state and syntax' => ["CREATE PROCEDURE p() BEGIN SIGNAL SQLSTATE '00000' SET MESSAGE_TEXT = +; END", 1046, [1046]];
        yield 'qualified routine zero code before syntax' => ['CREATE PROCEDURE d.p() BEGIN DECLARE c CONDITION FOR 0; SELECT +; END', 1525, [1525]];
        yield 'binary stops after refused operand' => ['SELECT BINARY CAST(1 AT LOCAL AS SIGNED),?', 1235, [1235, 1287]];
        yield 'duplicate common table aborts' => ['WITH x AS (SELECT !1), x AS (SELECT BINARY 2) SELECT !?', 1066, [1287, 1287, 1066]];
        yield 'collation before duplicate' => ['WITH x AS (SELECT 1 COLLATE missing), x AS (SELECT 2) SELECT ?', 1273, [1273, 1066]];
        yield 'type attributes before marker' => ['CREATE TABLE t(a TEXT BINARY ASCII, b INT DEFAULT ?)', 1064, [1287, 1287, 1064]];
        yield 'collation continues' => ['SELECT 1 COLLATE missing,?', 1273, [1273, 1064]];
        yield 'duplicates remain' => ['SELECT 1 COLLATE missing COLLATE missing,?', 1273, [1273, 1273, 1064]];
        yield 'interleaved warnings' => ['SELECT !1 COLLATE missing, BINARY 1,?', 1273, [1287, 1273, 1287, 1064]];
        yield 'charset aborts' => ['SELECT CONVERT(1 USING missing), !?, 1 COLLATE other', 1115, [1115]];
        yield 'cast charset aborts' => ['SELECT CAST(1 AS CHAR CHARACTER SET missing),?', 1115, [1115]];
        yield 'collation then abort' => ['SELECT 1 COLLATE missing, CONVERT(1 USING other),?', 1273, [1273, 1115]];
        yield 'empty collation resolves later' => ["SELECT 1 COLLATE '',?", 1064, [1064]];
        yield 'known collation' => ['SELECT 1 COLLATE utf8mb4_bin,?', 1064, [1064]];
        yield 'name after refusal' => ['SELECT ? COLLATE missing', 1064, [1064]];
    }

    /**
     * @param list<int> $codes The ordered condition numbers
     */
    #[DataProvider('providerFailures')]
    public function testRecordedKeepsTheFirstErrorAndAbortingNotices(string $sql, int $primary, array $codes): void
    {
        $session = (new Instance())->connect();
        $reply = $session->run($sql)[0];

        self::assertInstanceOf(SqlError::class, $reply);
        self::assertSame($primary, $reply->getCode());
        self::assertSame($codes, array_column($session->diagnostics->conditions, 1));
        self::assertSame(array_map(static fn (int $code): string => $code === 1287 ? 'Warning' : 'Error', $codes), array_column($session->diagnostics->conditions, 0));
    }

    /**
     * @return iterable<string, array{string, list<Deprecated>}>
     */
    public static function providerPrefixes(): iterable
    {
        yield 'bang' => ['SELECT !?', [Deprecated::BangNot]];
        yield 'adjacent pair' => ['SELECT !!?', [Deprecated::BangNot]];
        yield 'adjacent triple' => ['SELECT !!!?', [Deprecated::BangNot, Deprecated::BangNot]];
        yield 'separate marks' => ['SELECT ! !?', [Deprecated::BangNot, Deprecated::BangNot]];
        yield 'or' => ['SELECT 1 || ?', [Deprecated::PipesOr]];
        yield 'and' => ['SELECT 1 && ?', [Deprecated::AmpersandsAnd]];
        yield 'cache' => ['SELECT SQL_NO_CACHE ?', [Deprecated::NoCache]];
        yield 'repeated cache' => ['SELECT SQL_NO_CACHE SQL_NO_CACHE ?', [Deprecated::NoCache, Deprecated::NoCache]];
        yield 'found rows' => ['SELECT SQL_CALC_FOUND_ROWS ?', [Deprecated::CalcFoundRows]];
        yield 'completed binary' => ['SELECT BINARY 1,?', [Deprecated::BinaryOperator]];
        yield 'binary at rejected lookahead' => ['SELECT BINARY 1 ?', [Deprecated::BinaryOperator]];
        yield 'incomplete binary' => ['SELECT BINARY ?', []];
        yield 'completed assignment' => ['SELECT @a:=1,?', [Deprecated::AssignmentInExpression]];
        yield 'incomplete assignment' => ['SELECT @a:=1+?', []];
        yield 'nested expressions' => ['SELECT SQL_NO_CACHE @a:=BINARY 1,?', [Deprecated::NoCache, Deprecated::BinaryOperator, Deprecated::AssignmentInExpression]];
        yield 'nested query' => ['SELECT SQL_NO_CACHE (SELECT SQL_NO_CACHE !?),?', [Deprecated::NoCache, Deprecated::NoCache, Deprecated::BangNot]];
        yield 'quoted text' => ["SELECT 'SQL_NO_CACHE !',?", []];
        yield 'executable comment' => ['SELECT /*! SQL_NO_CACHE */ !?', [Deprecated::NoCache, Deprecated::BangNot]];
        yield 'earlier syntax refusal' => ['SELECT 1, SQL_NO_CACHE ?', []];
    }

    /**
     * @param list<Deprecated> $notices The expected notices, in native order
     */
    #[DataProvider('providerPrefixes')]
    public function testReportedPreservesNoticesBeforeTheUnpreparedMarker(string $sql, array $notices): void
    {
        $session = (new Instance())->connect();
        $session->run($sql);
        $actual = $session->diagnostics->conditions;
        $failure = array_pop($actual);

        self::assertSame(array_map(static fn (Deprecated $notice): array => ['Warning', $notice->code(), $notice->value], $notices), $actual);
        self::assertNotNull($failure);
        self::assertSame(1064, $failure[1]);
        self::assertSame([], $session->variables->user);
    }

    public function testBeforeKeepsThePipesAsConcatInterpretation(): void
    {
        $session = (new Instance())->connect();
        $session->variables->session['sql_mode'] = 'PIPES_AS_CONCAT';
        $parser = $session->semantics()->parser();
        $sql = 'SELECT 1 || ?';

        self::assertSame([], (new ModernWarnings())->before($parser->tokenize($sql), $sql, (int) strpos($sql, '?'), $parser));
    }

    public function testAcceptedRefusesATokenAfterAnEarlierGrammarError(): void
    {
        $parser = (new Instance())->connect()->semantics()->parser();
        $sql = 'SELECT ), !?';
        $tokens = $parser->tokenize($sql);

        self::assertFalse((new ModernWarnings())->accepted($parser, $sql, $tokens[3]));
        self::assertTrue((new ModernWarnings())->accepted($parser, 'SELECT 1', $parser->tokenize('SELECT 1')[1]));
    }

    public function testReportedLeavesLegacyAndNonSyntaxErrorsUnchanged(): void
    {
        $error = StatementError::ParseError->error('?', 1);
        $other = StatementError::NotSupportedYet->error('example');

        self::assertSame($error, (new ModernWarnings())->reported($error, 'SELECT !?', (new Instance('5.7.44'))->connect()));
        self::assertSame($other, (new ModernWarnings())->reported($other, 'SELECT !?', (new Instance())->connect()));
    }
}

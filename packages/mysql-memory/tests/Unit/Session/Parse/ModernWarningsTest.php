<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse;

use MySqlMemory\Error\Family\StatementError;
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

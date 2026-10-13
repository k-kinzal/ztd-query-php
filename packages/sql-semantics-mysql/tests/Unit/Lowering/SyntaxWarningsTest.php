<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;

#[CoversClass(SyntaxWarnings::class)]
#[Medium]
final class SyntaxWarningsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<Deprecated>}>
     */
    public static function providerExpressions(): iterable
    {
        yield 'completed binary' => ['SELECT BINARY 1,?', [Deprecated::BinaryOperator]];
        yield 'binary at rejected lookahead' => ['SELECT BINARY 1 ?', [Deprecated::BinaryOperator]];
        yield 'missing binary operand' => ['SELECT BINARY ?', []];
        yield 'incomplete binary group' => ['SELECT BINARY (1+?),2', []];
        yield 'binary before addition' => ['SELECT BINARY 1+?', [Deprecated::BinaryOperator]];
        yield 'completed assignment' => ['SELECT @a:=1,?', [Deprecated::AssignmentInExpression]];
        yield 'missing assignment operand' => ['SELECT @a:=?', []];
        yield 'incomplete assignment expression' => ['SELECT @a:=1+?', []];
        yield 'nested completions' => ['SELECT @a:=BINARY 1,?', [Deprecated::BinaryOperator, Deprecated::AssignmentInExpression]];
        yield 'nested binaries' => ['SELECT BINARY BINARY 1,?', [Deprecated::BinaryOperator, Deprecated::BinaryOperator]];
        yield 'system variable read' => ['SELECT @@autocommit,?', []];
        yield 'earlier syntax error' => ['SELECT ) BINARY 1,?', []];
    }

    /**
     * @param list<Deprecated> $expected The completed semantic notices
     */
    #[DataProvider('providerExpressions')]
    public function testCompletedUsesGrammarReductionsRatherThanTokenPrefixes(string $sql, array $expected): void
    {
        $warnings = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, (int) strpos($sql, '?'), GrammarRelease::MySql847);

        self::assertSame($expected, array_column($warnings, 1));
    }

    public function testCompletedKeepsLegacyVersionRules(): void
    {
        $sql = 'SELECT @a:=BINARY 1,?';

        self::assertSame([], (new SyntaxWarnings())->completed(new MySqlParser('mysql-5.7.44'), $sql, (int) strpos($sql, '?'), GrammarRelease::MySql5744));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Contract\LexicalSettings;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

#[CoversClass(SyntaxWarnings::class)]
#[Medium]
final class SyntaxWarningsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerEscapeModes(): iterable
    {
        yield 'backslash escapes' => ['', 'nope'];
        yield 'literal backslash' => ['NO_BACKSLASH_ESCAPES', 'no\\pe'];
    }

    #[DataProvider('providerEscapeModes')]
    public function testCompletedDecodesNamesUnderTheInputProfile(string $mode, string $name): void
    {
        $profile = new LanguageProfile(GrammarRelease::MySql847, new LexicalSettings($mode));
        $parser = (new Platform())->parser($profile);
        self::assertInstanceOf(\SqlParser\Parser\ReductionParser::class, $parser);
        $sql = "SELECT 1 COLLATE 'no\\pe',?";
        $warnings = (new SyntaxWarnings())->completed($parser, $sql, (int) strpos($sql, '?'), $profile);

        self::assertEquals([new ParseFailure(new UnknownCollation($name))], array_column($warnings, 1));
    }

    public function testCompletedKeepsContinuingErrorsBeforeTheFirstAbortingError(): void
    {
        $sql = "SELECT 1 COLLATE 'a''b', CONVERT(1 USING missing), BINARY 1,?";
        $warnings = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, (int) strpos($sql, '?'), new LanguageProfile(GrammarRelease::MySql847));

        self::assertEquals([new ParseFailure(new UnknownCollation("a'b")), new ParseFailure(new UnknownCharset('missing'), true)], array_column($warnings, 1));
    }

    /**
     * @return iterable<string, array{string, list<Deprecated>}>
     */
    public static function providerExpressions(): iterable
    {
        yield 'completed binary' => ['SELECT BINARY 1,?', [Deprecated::BinaryOperator]];
        yield 'national string' => ["SELECT N'a',?", [Deprecated::National]];
        yield 'national string segments' => ["SELECT N'a' 'b',?", [Deprecated::National]];
        yield 'national string in incomplete group' => ["SELECT BINARY (N'a'+?),2", [Deprecated::National]];
        yield 'national string before binary completion' => ["SELECT BINARY N'a',?", [Deprecated::National, Deprecated::BinaryOperator]];
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
        yield 'insert delayed' => ['INSERT DELAYED t VALUES (?)', [Deprecated::InsertDelayed]];
        yield 'replace delayed' => ['REPLACE DELAYED t VALUES (?)', [Deprecated::ReplaceDelayed]];
        yield 'commented insert modifier' => ['INSERT /* x */ DELAYED t VALUES (?)', [Deprecated::InsertDelayed]];
        yield 'rejected insert modifier' => ['INSERT HIGH_PRIORITY DELAYED t VALUES (?)', []];
    }

    /**
     * @param list<Deprecated> $expected The completed semantic notices
     */
    #[DataProvider('providerExpressions')]
    public function testCompletedUsesGrammarReductionsRatherThanTokenPrefixes(string $sql, array $expected): void
    {
        $warnings = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, (int) strpos($sql, '?'), new LanguageProfile(GrammarRelease::MySql847));

        self::assertSame($expected, array_column($warnings, 1));
    }

    public function testCompletedKeepsLegacyVersionRules(): void
    {
        $sql = 'SELECT @a:=BINARY 1,?';

        self::assertSame([], (new SyntaxWarnings())->completed(new MySqlParser('mysql-5.7.44'), $sql, (int) strpos($sql, '?'), new LanguageProfile(GrammarRelease::MySql5744)));
    }
}

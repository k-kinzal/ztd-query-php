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
    public function testCompletedStopsAtALoweredSyntaxRefusalWithoutReplacingIt(): void
    {
        $sql = 'CREATE PROCEDURE p() ALTER INSTANCE ROTATE BINLOG MASTER KEY';
        self::assertSame([], (new SyntaxWarnings())->completed(new MySqlParser('mysql-5.7.44'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql5744)));
    }

    public function testOrderedDiscardsOnlyNoticesAfterTheFirstStoppingCondition(): void
    {
        $stop = \SqlSemantics\Platform\MySql\Statement\Notice\ParseStop::ProgramStatement;
        $notices = [[30, Deprecated::BinaryOperator], [20, $stop], [10, Deprecated::BinaryOperator]];

        self::assertSame([[10, Deprecated::BinaryOperator], [20, $stop]], (new SyntaxWarnings())->ordered($notices));
    }

    public function testBeforeRetainsEarlierDuplicateDeclarations(): void
    {
        $sql = "CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE x INT; SELECT DATE 'bad'; END";
        $profile = new LanguageProfile(GrammarRelease::MySql847);
        $tree = (new Platform())->parser($profile)->parse($sql);
        $problem = (new SyntaxWarnings())->before($tree, strlen('CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE x INT; SELECT '), $profile);

        self::assertSame('Duplicate variable: x', $problem?->problem->message());
    }

    public function testCompletedKeepsAnInternalEntryBeforeALaterLexicalFailure(): void
    {
        $sql = "PARSE_GCOL_EXPR((SELECT a FROM .t)) 'unclosed";
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-5.7.44'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql5744));

        self::assertEquals([Deprecated::DotTable, new ParseFailure(new \SqlSemantics\Platform\MySql\Statement\Utility\Problem\GeneratedColumnEntry(0), true)], array_column($notices, 1));
    }

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

    public function testRejectedRetainsTheFirstInvalidConditionOperand(): void
    {
        $sql = "CREATE PROCEDURE p() BEGIN SIGNAL SQLSTATE '00000'; SIGNAL SQLSTATE 'bad'; END";
        $parser = new MySqlParser('mysql-8.4.7');
        $profile = new LanguageProfile(GrammarRelease::MySql847);
        $failure = (new SyntaxWarnings())->rejected($parser->parse($sql), $profile);

        self::assertInstanceOf(\SqlSemantics\Construction\RejectedInput::class, $failure);
        self::assertSame("Bad SQLSTATE: '00000'", $failure->problem->message());
        self::assertNull((new SyntaxWarnings())->rejected($parser->parse("SIGNAL SQLSTATE '45000'"), $profile));
    }
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerConditionOrder(): iterable
    {
        yield 'code before state' => ["CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR 0; SIGNAL SQLSTATE '00000'; END", "Incorrect CONDITION value: '0'"];
        yield 'state before nested code' => ["CREATE PROCEDURE p() BEGIN SIGNAL SQLSTATE '00000'; BEGIN DECLARE c CONDITION FOR 0; END; END", "Bad SQLSTATE: '00000'"];
        yield 'duplicate before code' => ['CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE x INT; DECLARE c CONDITION FOR 0; END', 'Duplicate variable: x'];
    }

    #[DataProvider('providerConditionOrder')]
    public function testRejectedPreservesOrderAcrossConditionKinds(string $sql, string $expected): void
    {
        $failure = (new SyntaxWarnings())->rejected((new MySqlParser('mysql-8.4.7'))->parse($sql), new LanguageProfile(GrammarRelease::MySql847));

        self::assertSame($expected, $failure?->problem->message());
    }

    public function testCompletedDiscardsNameConversionsAfterAnAbortingCondition(): void
    {
        $sql = 'CREATE PROCEDURE p() BEGIN SELECT CONVERT(1 USING missing); DROP RESOURCE GROUP `😀`; END';
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql847));

        self::assertEquals([new ParseFailure(new UnknownCharset('missing'), true)], array_column($notices, 1));
    }
    public function testEnclosuresLeavesARepresentableStructureWithAVersionedDiagnostic(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-5.6.51');
        $operation = $semantics->analyze('(SELECT 1) UNION SELECT 2');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\UnenclosedUnion::class, $operation->facts->diagnostics[0]);
        self::assertSame('(SELECT 1) UNION SELECT 2', $operation->toString());
    }

}

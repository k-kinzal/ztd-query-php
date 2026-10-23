<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Lowering\Productions;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Routine\Program\SyntaxArrays;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;

#[CoversClass(SyntaxArrays::class)]
#[Medium]
final class SyntaxArraysTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerBodies(): iterable
    {
        yield 'procedure' => ['CREATE PROCEDURE p() SELECT CAST(1 AS JSON ARRAY),'];
        yield 'function' => ['CREATE FUNCTION f() RETURNS INT RETURN CAST(1 AS TIME(7) ARRAY) +'];
        yield 'trigger' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW SET @v=CAST(1 AS SIGNED ARRAY) +'];
        yield 'event' => ['CREATE EVENT e ON SCHEDULE AT CURRENT_TIMESTAMP DO SELECT CAST(1 AS SIGNED ARRAY),'];
        yield 'alter event' => ['ALTER EVENT e DO SELECT CAST(1 AS SIGNED ARRAY),'];
    }

    #[DataProvider('providerBodies')]
    public function testCompletedDistinguishesStoredBodiesBeforeMalformedInput(string $sql): void
    {
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql847));

        self::assertEquals([new ParseFailure(new ProgramProblem(ProgramRule::ArrayCast), true)], array_column($notices, 1));
    }

    public function testCompletedLeavesTheEventScheduleOutsideTheBody(): void
    {
        $sql = 'CREATE EVENT e ON SCHEDULE AT CAST(1 AS SIGNED ARRAY) DO SELECT 1';
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql847));

        self::assertSame([], $notices);
    }

    public function testCompletedRetainsLaterWarningsInTheEarlyModernInstruction(): void
    {
        $sql = 'CREATE PROCEDURE p() SELECT CAST(1 AS SIGNED ARRAY), BINARY 1,';
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.0.44'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql8044));

        self::assertEquals([new ParseFailure(new ProgramProblem(ProgramRule::ArrayCast)), Deprecated::BinaryOperator], array_column($notices, 1));
    }

    public function testCompletedDoesNotAddAnOuterCastErrorAfterItsOperandWasRefused(): void
    {
        $sql = 'CREATE PROCEDURE p() SELECT CAST(CAST(1 AS TIME(7)) AS SIGNED ARRAY)';
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.0.44'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql8044));

        self::assertEquals(new ParseFailure(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\TooBigPrecision(7, 'CAST')), $notices[0][1]);
        self::assertCount(1, array_filter(array_column($notices, 1), static fn ($notice): bool => $notice instanceof ParseFailure));
    }

    public function testRejectedReportsTheClosingCastBoundary(): void
    {
        $sql = 'SELECT CAST(1 AS SIGNED ARRAY)';
        $tree = (new MySqlParser('mysql-8.4.7'))->parse($sql);
        $lowering = new Lowering(Productions::load(__DIR__ . '/../../../../../resources/productions/mysql-8.4.7.php'), new Leaves(), new LanguageProfile(GrammarRelease::MySql847));
        $refusal = (new SyntaxArrays())->rejected($tree->find('simple_expr')[0], $lowering);

        self::assertInstanceOf(ProgramProblem::class, $refusal?->problem);
        self::assertSame(ProgramRule::ArrayCast, $refusal->problem->rule);
        self::assertSame(strlen($sql), $refusal->end);
        self::assertNull((new SyntaxArrays())->rejected($tree, $lowering));
    }
    public function testUnavailableDistinguishesADirectCastFromAnExpressionContainingIt(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-8.0.44');
        $lowering = new Lowering(Productions::load(__DIR__ . '/../../../../../resources/productions/mysql-8.0.44.php'), new Leaves(), $semantics->profile());
        $direct = $semantics->parser()->parse('SELECT CAST(1 AS TIME(7))')->find('expr')[0];
        $compound = $semantics->parser()->parse('SELECT CAST(1 AS TIME(7)) + 1')->find('expr')[0];
        self::assertTrue((new SyntaxArrays())->unavailable($direct, $lowering));
        self::assertFalse((new SyntaxArrays())->unavailable($compound, $lowering));
    }

    public function testCompletedKeepsTheOuterRestrictionAfterACollationFailure(): void
    {
        $sql = 'CREATE PROCEDURE p() SELECT CAST(1 COLLATE missing AS SIGNED ARRAY)';
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.0.44'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql8044));
        self::assertInstanceOf(ParseFailure::class, $notices[0][1]);
        self::assertInstanceOf(ParseFailure::class, $notices[1][1]);
        self::assertSame("Unknown collation: 'missing'", $notices[0][1]->message());
        self::assertSame('Incorrect usage of CAST( .. AS .. ARRAY) and stored routines', $notices[1][1]->message());
    }
}

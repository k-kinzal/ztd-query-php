<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine\Condition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Lowering\Routine\Condition\SyntaxConditions;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;

#[CoversClass(SyntaxConditions::class)]
#[Medium]
final class SyntaxConditionsTest extends TestCase
{
    public function testCompletedRetainsARejectedOperandBeforeLaterSyntax(): void
    {
        $sql = "SIGNAL SQLSTATE '00000' SET MESSAGE_TEXT = +";
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql847));

        self::assertEquals([new ParseFailure(new ProgramProblem(ProgramRule::BadSqlState, '00000'), true)], array_column($notices, 1));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerRejectedCodes(): iterable
    {
        yield 'zero in a condition in 5.6' => ['mysql-5.6.51', 'CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR 0; END'];
        yield 'zero in a handler in 8.0' => ['mysql-8.0.44', 'CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR 0 BEGIN END; END'];
        yield 'a decimal below one in 9.1' => ['mysql-9.1.0', 'CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR 0.7; END'];
        yield 'a hexadecimal zero in 9.1' => ['mysql-9.1.0', 'CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR 0x00; END'];
    }

    #[DataProvider('providerRejectedCodes')]
    public function testCompletedRejectsZeroCodesInEachNumericForm(string $release, string $sql): void
    {
        $notices = (new SyntaxWarnings())->completed(new MySqlParser($release), $sql, strlen($sql), new LanguageProfile(GrammarRelease::from($release)));

        self::assertEquals([new ParseFailure(new ProgramProblem(ProgramRule::ZeroErrorCode), true)], array_column($notices, 1));
    }

    public function testCompletedKeepsTheCodeRefusalBeforeLaterSyntax(): void
    {
        $sql = 'CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR 0; SELECT +; END';
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql847));

        self::assertEquals([new ParseFailure(new ProgramProblem(ProgramRule::ZeroErrorCode), true)], array_column($notices, 1));
    }

    public function testCompletedAcceptsAValidOperand(): void
    {
        $sql = "SIGNAL SQLSTATE '45000'";
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql847));

        self::assertSame([], $notices);
    }
}

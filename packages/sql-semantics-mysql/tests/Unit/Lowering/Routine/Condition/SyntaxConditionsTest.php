<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine\Condition;

use PHPUnit\Framework\Attributes\CoversClass;
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

    public function testCompletedAcceptsAValidOperand(): void
    {
        $sql = "SIGNAL SQLSTATE '45000'";
        $notices = (new SyntaxWarnings())->completed(new MySqlParser('mysql-8.4.7'), $sql, strlen($sql), new LanguageProfile(GrammarRelease::MySql847));

        self::assertSame([], $notices);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Lowering\Routine\Program\SyntaxDefinitions;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedProgramBody;

#[CoversClass(SyntaxDefinitions::class)]
#[Medium]
final class SyntaxDefinitionsTest extends TestCase
{
    public function testRejectedPreservesTheOuterHeaderBeforeANestedFunctionOperand(): void
    {
        $sql = "CREATE PROCEDURE d.p() CREATE DEFINER='u'@'localhost' FUNCTION f() RETURNS INT RETURN CAST(1 AS SIGNED ARRAY)";
        $tree = (new MySqlParser('mysql-8.4.7'))->parse($sql);
        $failure = (new SyntaxWarnings())->rejected($tree, new LanguageProfile(GrammarRelease::MySql847));

        self::assertInstanceOf(RefusedProgramBody::class, $failure?->problem);
        self::assertInstanceOf(ProgramProblem::class, $failure->problem->problem);
        self::assertSame(ProgramRule::RecursiveCreate, $failure->problem->problem->rule);
        self::assertSame('p', $failure->problem->name->name->value);
        self::assertNull($failure->problem->definer);
        self::assertLessThan(strpos($sql, 'CAST'), $failure->end);
    }

    public function testRejectedLeavesAnEventChangeWithoutABodyAlone(): void
    {
        $tree = (new MySqlParser('mysql-8.4.7'))->parse("ALTER EVENT e COMMENT 'x'");

        self::assertNull((new SyntaxDefinitions())->rejected($tree->find('alter_event_stmt')[0]));
    }
}

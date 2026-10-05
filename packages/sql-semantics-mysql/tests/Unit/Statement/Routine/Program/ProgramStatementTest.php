<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ConditionDeclaration;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\CloseCursor;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\FetchCursor;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\OpenCursor;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ConditionalBranch;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\IfStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\Iterate;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\Leave;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\Loop;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\RepeatLoop;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\SearchedCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\SimpleCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\WhileLoop;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\VariableDeclaration;

#[CoversClass(ProgramStatement::class)]
#[Medium]
final class ProgramStatementTest extends TestCase
{
    public function testDeriveProgramIsImplementedByTheStatementsOfThePrograms(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN SELECT 1; END')->statement;
        self::assertInstanceOf(CreateProcedure::class, $create);
        $block = $create->body;
        self::assertInstanceOf(Block::class, $block);

        self::assertNotInstanceOf(ProgramStatement::class, $block->statements[0]);
        self::assertInstanceOf(Select::class, $block->statements[0]);
        self::assertTrue(is_subclass_of(OpenCursor::class, ProgramStatement::class));
        self::assertTrue(is_subclass_of(FetchCursor::class, ProgramStatement::class));
        self::assertTrue(is_subclass_of(CloseCursor::class, ProgramStatement::class));
        self::assertTrue(is_subclass_of(IfStatement::class, ProgramStatement::class));
        self::assertTrue(is_subclass_of(SimpleCase::class, ProgramStatement::class));
        self::assertTrue(is_subclass_of(SearchedCase::class, ProgramStatement::class));
        self::assertTrue(is_subclass_of(Loop::class, ProgramStatement::class));
        self::assertTrue(is_subclass_of(WhileLoop::class, ProgramStatement::class));
        self::assertTrue(is_subclass_of(RepeatLoop::class, ProgramStatement::class));
        self::assertTrue(is_subclass_of(Leave::class, ProgramStatement::class));
        self::assertTrue(is_subclass_of(Iterate::class, ProgramStatement::class));
        self::assertTrue(is_subclass_of(ReturnStatement::class, ProgramStatement::class));
        self::assertFalse(is_subclass_of(ConditionalBranch::class, ProgramStatement::class));
        self::assertFalse(is_subclass_of(VariableDeclaration::class, ProgramStatement::class));
        self::assertFalse(is_subclass_of(ConditionDeclaration::class, ProgramStatement::class));
    }
}

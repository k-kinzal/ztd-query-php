<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
    public function testDeriveProgramIsNotImplementedByAQueryOfABody(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN SELECT 1; END')->statement;
        self::assertInstanceOf(CreateProcedure::class, $create);
        $block = $create->body;
        self::assertInstanceOf(Block::class, $block);

        self::assertNotInstanceOf(ProgramStatement::class, $block->statements[0]);
        self::assertInstanceOf(Select::class, $block->statements[0]);
    }

    #[DataProvider('providerDeriveProgramIsImplementedByTheProgramStatementClasses')]
    public function testDeriveProgramIsImplementedByTheProgramStatementClasses(string $class, bool $expected): void
    {
        self::assertSame($expected, is_subclass_of($class, ProgramStatement::class));
    }

    /**
     * @return iterable<string, array{class-string, bool}>
     */
    public static function providerDeriveProgramIsImplementedByTheProgramStatementClasses(): iterable
    {
        yield 'OPEN' => [OpenCursor::class, true];
        yield 'FETCH' => [FetchCursor::class, true];
        yield 'CLOSE' => [CloseCursor::class, true];
        yield 'IF' => [IfStatement::class, true];
        yield 'a simple CASE' => [SimpleCase::class, true];
        yield 'a searched CASE' => [SearchedCase::class, true];
        yield 'LOOP' => [Loop::class, true];
        yield 'WHILE' => [WhileLoop::class, true];
        yield 'REPEAT' => [RepeatLoop::class, true];
        yield 'LEAVE' => [Leave::class, true];
        yield 'ITERATE' => [Iterate::class, true];
        yield 'RETURN' => [ReturnStatement::class, true];
        yield 'a branch' => [ConditionalBranch::class, false];
        yield 'a variable declaration' => [VariableDeclaration::class, false];
        yield 'a condition declaration' => [ConditionDeclaration::class, false];
    }
}

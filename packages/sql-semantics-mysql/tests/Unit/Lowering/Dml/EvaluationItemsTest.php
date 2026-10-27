<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Lowering\Productions;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Dml\EvaluationItems;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(EvaluationItems::class)]
#[Medium]
final class EvaluationItemsTest extends TestCase
{
    public function testReadRejectsAStarBeforePublishingAnEvaluation(): void
    {
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage('No tables used');

        (new Semantics(Dialect::MySql))->analyze('DO *');
    }

    public function testProgramRetainsWildcardsOnlyInAStoredInstruction(): void
    {
        $body = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() DO *');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $body);

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredEvaluation::class, $body->body);
    }

    public function testRequiresDatabaseRecognizesOnlyUnqualifiedStoredCalls(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $reader = new EvaluationItems(new Lowering(new Productions([]), new Leaves(), $semantics->profile()));

        self::assertSame(true, $reader->requiresDatabase([new SelectExpression(new FunctionCall(new Name('missing')))]));
        self::assertSame(false, $reader->requiresDatabase([new SelectExpression(new FunctionCall(new Name('missing'), schema: new Name('d')))]));
    }

    public function testStoredKeepsTheDeferredOperandAsAProgramMember(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');
        $operation = $semantics->analyze('CREATE PROCEDURE p() DO (1, 2) COLLATE missing');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredEvaluation::class, $operation->body);
        self::assertSame([], $operation->diagnostics());
        self::assertSame('CREATE PROCEDURE p() DO (1, 2) COLLATE missing', $operation->toString());
    }

}

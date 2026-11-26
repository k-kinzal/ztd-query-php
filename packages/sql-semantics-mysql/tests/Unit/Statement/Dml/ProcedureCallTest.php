<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Dependent;
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ProcedureCall::class)]
#[Medium]
final class ProcedureCallTest extends TestCase
{
    public function testDeriveStatementDerivesTheArguments(): void
    {
        $operation = (new Inspection())->inspect('CALL p(1, @x)');
        self::assertInstanceOf(ProcedureCall::class, $operation->statement);

        self::assertInstanceOf(Dependent::class, $operation->facts->scalar($operation->statement->arguments[1])->type);
        self::assertNull($operation->facts->output);
    }

    public function testRenderWritesParenthesesOnlyForArguments(): void
    {
        self::assertSame('CALL p', (new Inspection())->inspect('call p()')->toString());
        self::assertSame('CALL db.p(1)', (new Inspection('mysql-5.6.51'))->inspect('call db.p (1)')->toString());
    }
}

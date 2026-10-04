<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;
use SqlSemantics\Statement\Type\Dependent;

#[CoversClass(ProcedureCall::class)]
#[Medium]
final class ProcedureCallTest extends TestCase
{
    public function testDeriveStatementDerivesTheArguments(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CALL p(1, @x)');
        self::assertInstanceOf(ProcedureCall::class, $operation->statement);

        self::assertInstanceOf(Dependent::class, $operation->facts->scalar($operation->statement->arguments[1])->type);
        self::assertNull($operation->facts->output);
    }

    public function testRenderWritesParenthesesOnlyForArguments(): void
    {
        self::assertSame('CALL p', (new Semantics(Dialect::MySql))->analyze('call p()')->toString());
        self::assertSame('CALL db.p(1)', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('call db.p (1)')->toString());
    }
}

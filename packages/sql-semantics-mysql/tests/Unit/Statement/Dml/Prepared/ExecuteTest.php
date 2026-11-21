<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Prepared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Dependent;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Execute;

#[CoversClass(Execute::class)]
#[Medium]
final class ExecuteTest extends TestCase
{
    public function testDeriveStatementDerivesTheVariables(): void
    {
        $operation = (new Semantics())->inspect('EXECUTE s USING @a');
        self::assertInstanceOf(Execute::class, $operation->statement);

        self::assertInstanceOf(Dependent::class, $operation->facts->scalar($operation->statement->variables[0])->type);
    }

    public function testRenderWritesTheVariables(): void
    {
        self::assertSame('EXECUTE s', (new Semantics())->inspect('execute s')->toString());
        self::assertSame('EXECUTE s USING @a, @b', (new Semantics('mysql-5.7.44'))->inspect('execute s using @a, @b')->toString());
    }
}

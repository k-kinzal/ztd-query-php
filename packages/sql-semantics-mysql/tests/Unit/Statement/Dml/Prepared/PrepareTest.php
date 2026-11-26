<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Prepared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Dependent;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Prepare;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(Prepare::class)]
#[Medium]
final class PrepareTest extends TestCase
{
    public function testDeriveStatementDerivesTheVariable(): void
    {
        $operation = (new Inspection())->inspect('PREPARE s FROM @q');
        self::assertInstanceOf(Prepare::class, $operation->statement);
        self::assertInstanceOf(UserVariable::class, $operation->statement->source);

        self::assertInstanceOf(Dependent::class, $operation->facts->scalar($operation->statement->source)->type);
    }

    public function testRenderWritesTheSource(): void
    {
        self::assertSame("PREPARE s FROM 'SELECT ?'", (new Inspection('mysql-5.6.51'))->inspect("prepare s from 'SELECT ?'")->toString());
    }
}

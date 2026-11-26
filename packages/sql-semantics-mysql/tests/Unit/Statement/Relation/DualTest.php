<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(Dual::class)]
#[Medium]
final class DualTest extends TestCase
{
    public function testDeriveRelationAnswersTheEmptyRow(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1 FROM DUAL');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(Dual::class, $operation->statement->from);
        self::assertSame([], $operation->facts->relation($operation->statement->from)->shape->slots);
        self::assertTrue($operation->facts->relation($operation->statement->from)->shape->complete());
    }

    public function testRenderWritesTheKeyword(): void
    {
        self::assertSame('SELECT 1 FROM DUAL', (new Inspection('mysql-5.6.51'))->inspect('select 1 from dual')->toString());
        self::assertSame('SELECT 1 FROM DUAL', (new Inspection('mysql-5.7.44'))->inspect('select 1 from dual')->toString());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;

#[CoversClass(Dual::class)]
#[Medium]
final class DualTest extends TestCase
{
    public function testDeriveRelationAnswersTheEmptyRow(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1 FROM DUAL');

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation);
        self::assertInstanceOf(Select::class, $operation->query);
        self::assertInstanceOf(Dual::class, $operation->query->from);
        self::assertSame([], $operation->query->from->relationInformation()->shape->slots);
        self::assertTrue($operation->query->from->relationInformation()->shape->complete());
    }

    public function testRenderWritesTheKeyword(): void
    {
        self::assertSame('SELECT 1 FROM DUAL', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select 1 from dual')->toString());
        self::assertSame('SELECT 1 FROM DUAL', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select 1 from dual')->toString());
    }
}

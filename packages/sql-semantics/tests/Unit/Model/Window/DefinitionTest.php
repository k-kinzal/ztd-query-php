<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Window;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Window\Definition;
use SqlSemantics\Model\Window\WindowSpecification;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Definition::class)]
#[Medium]
final class DefinitionTest extends TestCase
{
    public function testAssociatesTheWindowNameWithItsSpecification(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INTEGER NOT NULL, x INTEGER)'));
        $query = $binder->bind('SELECT sum(id) OVER w FROM t WINDOW w AS (PARTITION BY x)');
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertCount(1, $query->windows);
        $definition = $query->windows[0];
        self::assertSame('w', $definition->name);
        self::assertNull($definition->specification->base);
        self::assertSame('x', $definition->specification->partitionBy[0]->columnBinding()?->column->name);
        self::assertSame('SELECT sum(`id`) OVER `w` FROM `t` WINDOW `w` AS (PARTITION BY `x`)', $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    public function testExposesTheSuppliedOperands(): void
    {
        $specification = new WindowSpecification(null, [], [], null);
        $definition = new Definition('w', $specification);
        self::assertSame('w', $definition->name);
        self::assertSame($specification, $definition->specification);
    }
}

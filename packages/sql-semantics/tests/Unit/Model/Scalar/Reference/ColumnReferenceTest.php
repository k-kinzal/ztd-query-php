<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Model\Scalar\Reference\ColumnReference::class)]
final class ColumnReferenceTest extends TestCase
{
    public function testRetainsAMandatoryBindingToTheDeclaredColumn(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
        $query = (new Binder($schema))->bind('SELECT t.id FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\ColumnReference::class, $value);
        self::assertSame('id', $value->binding->column->name);
        self::assertSame($query->relations[0]->id, $value->binding->relationId);
    }
}

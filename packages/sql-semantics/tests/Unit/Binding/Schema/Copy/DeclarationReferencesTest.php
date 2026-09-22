<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Schema\Copy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binding\Schema\Copy\DeclarationReferences;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Reference\ColumnReference;
use SqlSemantics\Model\Traversal\Expressions;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DeclarationReferences::class)]
final class DeclarationReferencesTest extends TestCase
{
    public function testRebindUpdatesEveryIndependentReference(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE base(id INTEGER PRIMARY KEY, x INTEGER, d INTEGER GENERATED ALWAYS AS(x*2) STORED, CHECK (x>0), KEY ix(x))', 'CREATE TABLE copy LIKE base');
        $references = array_values(array_filter(Expressions::all($schema->tables[1]), static fn (Expression $value): bool => $value instanceof ColumnReference));
        self::assertCount(4, $references);
        self::assertSame(['copy'], array_values(array_unique(array_map(static fn (ColumnReference $value): string => $value->columnBinding()->table->name, $references))));
        self::assertSame($schema->tables[1], DeclarationReferences::rebind($schema->tables[1], $schema->tables[0]));
    }
}

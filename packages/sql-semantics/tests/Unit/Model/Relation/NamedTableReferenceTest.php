<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\NamedTableReference;
use SqlSemantics\Model\Statement\TableStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(NamedTableReference::class)]
#[Medium]
final class NamedTableReferenceTest extends TestCase
{
    public function testResultExpressionsDescribesStorageWithoutFabricatingValues(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
        $statement = (new Binder($schema))->bind('TABLE t');
        self::assertInstanceOf(TableStatement::class, $statement);
        self::assertInstanceOf(NamedTableReference::class, $statement->from);
        self::assertSame($schema->tables[0], $statement->from->declaration);
        self::assertSame([], $statement->from->resultExpressions());
        self::assertSame($schema->tables[0]->columns[0]->type, $statement->outputs[0]->expression->type);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Relation\TableIdentity;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TableIdentity::class)]
#[Medium]
final class TableIdentityTest extends TestCase
{
    public function testExposesTheNamespaceAndName(): void
    {
        $identity = new TableIdentity('public', 't');
        self::assertSame('public', $identity->schema);
        self::assertSame('t', $identity->name);
    }

    #[TestWith([Dialect::PostgreSql, 'public'])]
    #[TestWith([Dialect::Sqlite, 'main'])]
    public function testIdentifiesTheDeclaredTableOfAColumnReference(Dialect $dialect, string $schema): void
    {
        $query = (new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(id INTEGER)')))->bind('SELECT a.id FROM t AS a');
        self::assertInstanceOf(BoundSelect::class, $query);
        $binding = $query->outputs[0]->expression->columnBinding();
        self::assertNotNull($binding);
        self::assertSame($schema, $binding->table->schema);
        self::assertSame('t', $binding->table->name);
    }
}

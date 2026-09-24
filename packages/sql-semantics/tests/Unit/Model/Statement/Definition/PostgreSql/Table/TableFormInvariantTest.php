<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation\Foreign\PartitionColumn;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Table\TableFormInvariant;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Table\PostgreSqlProperties;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TableFormInvariant::class)]
#[Medium]
final class TableFormInvariantTest extends TestCase
{
    public function testCheckAcceptsDistinctOverrides(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        TableFormInvariant::check($origin, [new PartitionColumn('a'), new PartitionColumn('b')], [], new PostgreSqlProperties());
        $this->expectNotToPerformAssertions();
    }

    public function testCheckRejectsARepeatedOverride(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('A column is overridden at most once.');
        TableFormInvariant::check($origin, [new PartitionColumn('a'), new PartitionColumn('a')], [], new PostgreSqlProperties());
    }

    public function testCheckRejectsInheritance(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('Partitions and typed tables cannot declare INHERITS.');
        TableFormInvariant::check($origin, [], [], new PostgreSqlProperties(parents: [new QualifiedName(['a'])]));
    }

    public function testCheckRejectsAnotherDatabaseLanguage(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        TableFormInvariant::check($origin, [], [], new PostgreSqlProperties());
    }
}

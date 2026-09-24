<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Key\AddIndex;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\IndexDefinition;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AddIndex::class)]
#[Medium]
final class AddIndexTest extends TestCase
{
    public function testReadsTheIndex(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ADD FULLTEXT INDEX ft (n)');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(AddIndex::class, $alteration);
        self::assertSame('ft', $alteration->index->name);
        self::assertSame(['t'], $alteration->index->table);
    }

    public function testRejectsAUniqueIndex(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ADD INDEX ix (n)');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(AddIndex::class, $alteration);
        $index = $alteration->index;
        $this->expectException(InvalidStructure::class);
        new AddIndex(new IndexDefinition($index->schema, $index->name, $index->table, $index->elements, true, $index->method, $index->include, $index->predicate, $index->source, $index->properties));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Schema\IndexPropertiesBinder::class)]
#[Medium]
final class IndexPropertiesBinderTest extends TestCase
{
    public function testBindClassifiesMySqlIndexOptions(): void
    {
        $index = (new SchemaBuilder(Dialect::MySql))->build("CREATE TABLE t(a INT, KEY ix (a) KEY_BLOCK_SIZE=8 COMMENT 'k' INVISIBLE)")->tables[0]->indexes[0];
        self::assertSame(\SqlSemantics\Schema\Index\Kind::Ordinary, $index->properties->kind);
        self::assertFalse($index->properties->visible);
        self::assertSame(8, $index->properties->keyBlockSize);
        self::assertSame('k', $index->properties->comment);
        self::assertTrue($index->properties->nullsDistinct);
        self::assertSame([], $index->properties->storageParameters);
    }

    public function testBindClassifiesTheIndexKindAndComment(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT, b VARCHAR(10))')))->bind("CREATE FULLTEXT INDEX fx ON t (b) COMMENT 'full'");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CreateIndexStatement::class, $statement);
        $properties = $statement->index->definition->properties;
        self::assertSame(\SqlSemantics\Schema\Index\Kind::FullText, $properties->kind);
        self::assertSame('full', $properties->comment);
        self::assertNull($properties->visible);
        self::assertNull($properties->keyBlockSize);
    }

    public function testBindReadsPostgreSqlStorageParametersAndNullsDistinct(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)'));
        $stored = $binder->bind('CREATE INDEX ix ON t (a) WITH (fillfactor = 70)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CreateIndexStatement::class, $stored);
        self::assertSame(['fillfactor'], array_map(static fn (\SqlSemantics\Schema\Storage\Parameter $parameter): string => implode('.', $parameter->name->parts), $stored->index->definition->properties->storageParameters));
        self::assertTrue($stored->index->definition->properties->nullsDistinct);
        $unique = $binder->bind('CREATE UNIQUE INDEX ux ON t (a) NULLS NOT DISTINCT');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CreateIndexStatement::class, $unique);
        self::assertFalse($unique->index->definition->properties->nullsDistinct);
    }
}

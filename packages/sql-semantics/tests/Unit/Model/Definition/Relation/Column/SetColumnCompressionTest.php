<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Relation\Column\SetColumnCompression::class)]
#[Medium]
final class SetColumnCompressionTest extends TestCase
{
    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER COLUMN id SET COMPRESSION lz4', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertEquals(new Relation\Column\SetColumnCompression('id', Relation\Column\ColumnCompression::Lz4), $statement->actions[0]);
        self::assertSame('ALTER TABLE "t" ALTER COLUMN "id" SET COMPRESSION lz4', $statement->toString());
    }

    public function testRejectsAnEmptyColumn(): void
    {
        $this->expectException(InvalidStructure::class);
        new Relation\Column\SetColumnCompression('', Relation\Column\ColumnCompression::Pglz);
    }
}

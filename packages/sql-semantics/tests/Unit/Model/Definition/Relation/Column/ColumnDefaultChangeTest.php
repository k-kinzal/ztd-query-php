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

#[CoversClass(Relation\Column\ColumnDefaultChange::class)]
#[Medium]
final class ColumnDefaultChangeTest extends TestCase
{
    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER id SET DEFAULT 7, ALTER COLUMN id DROP DEFAULT', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertInstanceOf(Relation\Column\ColumnDefaultChange::class, $statement->actions[0]);
        self::assertSame('7', $statement->actions[0]->default?->structure()->toString());
        self::assertEquals(new Relation\Column\ColumnDefaultChange('id', null), $statement->actions[1]);
        self::assertSame('ALTER TABLE "t" ALTER COLUMN "id" SET DEFAULT 7, ALTER COLUMN "id" DROP DEFAULT', $statement->toString());
    }

    public function testRejectsAnEmptyColumn(): void
    {
        $this->expectException(InvalidStructure::class);
        new Relation\Column\ColumnDefaultChange('', null);
    }

    public function testRejectsAnExpressionFromAnotherDatabaseLanguage(): void
    {
        $default = \SqlSemantics\Model\Expression::literal(1, Dialect::MySql);
        $this->expectException(InvalidStructure::class);
        new Relation\Column\ColumnDefaultChange('id', $default);
    }
}

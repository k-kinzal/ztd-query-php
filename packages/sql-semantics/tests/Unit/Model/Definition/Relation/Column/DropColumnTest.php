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

#[CoversClass(Relation\Column\DropColumn::class)]
#[Medium]
final class DropColumnTest extends TestCase
{
    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t DROP COLUMN IF EXISTS id CASCADE, DROP id', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertEquals([new Relation\Column\DropColumn('id', true, \SqlSemantics\Model\Definition\DropBehavior::Cascade), new Relation\Column\DropColumn('id')], $statement->actions);
        self::assertSame('ALTER TABLE "t" DROP COLUMN IF EXISTS "id" CASCADE, DROP COLUMN "id"', $statement->toString());
    }

    public function testRejectsAnEmptyColumn(): void
    {
        $this->expectException(InvalidStructure::class);
        new Relation\Column\DropColumn('');
    }
}

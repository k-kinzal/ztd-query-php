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

#[CoversClass(Relation\Column\DropColumnExpression::class)]
#[Medium]
final class DropColumnExpressionTest extends TestCase
{
    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER COLUMN id DROP EXPRESSION IF EXISTS', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertEquals(new Relation\Column\DropColumnExpression('id', true), $statement->actions[0]);
        self::assertSame('ALTER TABLE "t" ALTER COLUMN "id" DROP EXPRESSION IF EXISTS', $statement->toString());
    }

    public function testRejectsAnEmptyColumn(): void
    {
        $this->expectException(InvalidStructure::class);
        new Relation\Column\DropColumnExpression('');
    }
}

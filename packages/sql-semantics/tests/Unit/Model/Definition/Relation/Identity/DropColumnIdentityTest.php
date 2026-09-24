<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Identity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Relation\Identity\DropColumnIdentity::class)]
#[Medium]
final class DropColumnIdentityTest extends TestCase
{
    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER COLUMN id DROP IDENTITY IF EXISTS', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertEquals(new Relation\Identity\DropColumnIdentity('id', true), $statement->actions[0]);
        self::assertSame('ALTER TABLE "t" ALTER COLUMN "id" DROP IDENTITY IF EXISTS', $statement->toString());
    }

    public function testRejectsAnEmptyColumn(): void
    {
        $this->expectException(InvalidStructure::class);
        new Relation\Identity\DropColumnIdentity('');
    }
}

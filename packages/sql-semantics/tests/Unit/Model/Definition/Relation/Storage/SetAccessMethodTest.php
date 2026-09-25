<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Relation\Storage\SetAccessMethod::class)]
#[Medium]
final class SetAccessMethodTest extends TestCase
{
    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t SET ACCESS METHOD DEFAULT, SET ACCESS METHOD heap', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertEquals([new Relation\Storage\SetAccessMethod(null), new Relation\Storage\SetAccessMethod('heap')], $statement->actions);
        self::assertSame('ALTER TABLE "t" SET ACCESS METHOD DEFAULT, SET ACCESS METHOD "heap"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testRejectsAnEmptyMethod(): void
    {
        $this->expectException(InvalidStructure::class);
        new Relation\Storage\SetAccessMethod('');
    }
}

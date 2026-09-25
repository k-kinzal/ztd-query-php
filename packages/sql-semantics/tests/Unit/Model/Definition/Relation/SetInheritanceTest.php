<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Relation\SetInheritance::class)]
#[Medium]
final class SetInheritanceTest extends TestCase
{
    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t NO INHERIT app.base, INHERIT base', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertEquals([new Relation\SetInheritance(new QualifiedName(['app', 'base']), false), new Relation\SetInheritance(new QualifiedName(['base']), true)], $statement->actions);
        self::assertSame('ALTER TABLE "t" NO INHERIT "app"."base", INHERIT "base"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testRejectsAnOverQualifiedParent(): void
    {
        $this->expectException(InvalidStructure::class);
        new Relation\SetInheritance(new QualifiedName(['a', 'b', 'c', 'd']), true);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Relation\RelationProperty::class)]
#[Medium]
final class RelationPropertyTest extends TestCase
{
    public function testSpellsEachChoiceAsItsKeywords(): void
    {
        self::assertSame(['SET WITHOUT OIDS', 'SET WITHOUT CLUSTER', 'NOT OF'], array_column(Relation\RelationProperty::cases(), 'value'));
    }

    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t SET WITHOUT CLUSTER, SET WITHOUT OIDS', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertEquals([new Relation\RemoveRelationProperty(Relation\RelationProperty::Cluster), new Relation\RemoveRelationProperty(Relation\RelationProperty::Oids)], $statement->actions);
        self::assertSame('ALTER TABLE "t" SET WITHOUT CLUSTER, SET WITHOUT OIDS', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}

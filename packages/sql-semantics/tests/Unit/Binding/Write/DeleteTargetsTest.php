<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Write\DeleteTargets;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Statement\Mutation\DeleteJoinedStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DeleteTargets::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class DeleteTargetsTest extends TestCase
{
    public function testTablesRetainsNamedDestinations(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INTEGER)', 'CREATE TABLE u(id INTEGER)');
        $query = (new Binder($schema))->bind('DELETE t FROM t JOIN u USING (id)');
        self::assertInstanceOf(DeleteJoinedStatement::class, $query);
        self::assertSame($query->targets, DeleteTargets::tables($query->targets, $query->origin->source));
    }

    public function testTablesRejectsADerivedReadRelation(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT * FROM (SELECT 1 AS id) AS d');
        self::assertInstanceOf(BoundSelect::class, $query);
        $this->expectException(\SqlSemantics\InvalidSql::class);
        DeleteTargets::tables($query->relations, $query->origin->source);
    }
}

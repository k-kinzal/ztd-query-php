<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Replication\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Replication\Filter\DatabaseRewrite;
use SqlSemantics\Model\Configuration\Replication\Filter\RewriteFilter;
use SqlSemantics\Model\Statement\Server\Replication\ChangeReplicationFilterStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DatabaseRewrite::class)]
#[Medium]
final class DatabaseRewriteTest extends TestCase
{
    public function testKeepsTheSourceAndTargetDatabases(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CHANGE REPLICATION FILTER REPLICATE_REWRITE_DB = ((a, b), (c, `d`))');
        self::assertInstanceOf(ChangeReplicationFilterStatement::class, $statement);
        self::assertEquals([new RewriteFilter([new DatabaseRewrite('a', 'b'), new DatabaseRewrite('c', 'd')])], $statement->filters);
    }
}

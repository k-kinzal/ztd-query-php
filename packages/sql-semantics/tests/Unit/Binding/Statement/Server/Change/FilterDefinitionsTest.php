<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Server\Change;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Server\Change\FilterDefinitions;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Replication\Filter\DatabaseFilter;
use SqlSemantics\Model\Configuration\Replication\Filter\TableFilter;
use SqlSemantics\Model\Statement\Server\Replication\ChangeReplicationFilterStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(FilterDefinitions::class)]
#[Medium]
final class FilterDefinitionsTest extends TestCase
{
    public function testReadDecodesQuotedDatabaseNames(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CHANGE REPLICATION FILTER REPLICATE_IGNORE_DB = (`x y`, PERSIST)');
        self::assertInstanceOf(ChangeReplicationFilterStatement::class, $statement);
        self::assertInstanceOf(DatabaseFilter::class, $statement->filters[0]);
        self::assertSame(['x y', 'PERSIST'], $statement->filters[0]->databases);
    }

    public function testTableReadsTheDatabaseAndTable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CHANGE REPLICATION FILTER REPLICATE_DO_TABLE = (`d.b`.`t`)');
        self::assertInstanceOf(ChangeReplicationFilterStatement::class, $statement);
        self::assertInstanceOf(TableFilter::class, $statement->filters[0]);
        self::assertSame(['d.b', 't'], $statement->filters[0]->tables[0]->parts);
        self::assertSame('CHANGE REPLICATION FILTER REPLICATE_DO_TABLE = (`d.b`.`t`)', $statement->toString());
    }
}

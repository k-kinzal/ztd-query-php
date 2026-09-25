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
        self::assertSame('CHANGE REPLICATION FILTER REPLICATE_DO_TABLE = (`d.b`.`t`)', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testReadSpellsEveryLowercaseRule(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("change replication filter replicate_do_db = (a, b), replicate_ignore_db = (c), replicate_do_table = (d.t), replicate_ignore_table = (d.u), replicate_wild_do_table = ('d.%'), replicate_wild_ignore_table = ('e.%'), replicate_rewrite_db = ((a, b), (c, d))");
        self::assertSame("CHANGE REPLICATION FILTER REPLICATE_DO_DB = (`a`, `b`), REPLICATE_IGNORE_DB = (`c`), REPLICATE_DO_TABLE = (`d`.`t`), REPLICATE_IGNORE_TABLE = (`d`.`u`), REPLICATE_WILD_DO_TABLE = ('d.%'), REPLICATE_WILD_IGNORE_TABLE = ('e.%'), REPLICATE_REWRITE_DB = ((`a`, `b`), (`c`, `d`))", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testReadRejectsAWildcardWithoutADot(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $binder->bind("CHANGE REPLICATION FILTER REPLICATE_WILD_DO_TABLE = ('nodot')");
    }

    public function testTableReadsATableIdentifierNode(): void
    {
        $node = \SqlSemantics\Ast\Tree::outer((new \SqlSemantics\Ast\DialectParser(Dialect::MySql))->parse('CHANGE REPLICATION FILTER REPLICATE_DO_TABLE = (d.`t`)'), ['filter_table_ident'])[0];
        self::assertSame(['d', 't'], FilterDefinitions::table($node, new \SqlSemantics\Ast\Identifiers(Dialect::MySql))->parts);
    }
}

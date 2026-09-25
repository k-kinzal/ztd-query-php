<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\Definition\View\MySqlViewProperties;
use SqlSemantics\Model\Definition\View\ViewAlgorithm;
use SqlSemantics\Model\Definition\View\ViewSecurity;
use SqlSemantics\Model\Definition\ViewCheck;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\MySql\View\AlterViewStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterViewStatement::class)]
#[Medium]
final class AlterViewStatementTest extends TestCase
{
    public function testWithOriginRetainsTheAlteration(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER VIEW v (a) AS SELECT 1 WITH CHECK OPTION');
        self::assertInstanceOf(AlterViewStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame('ALTER VIEW `v`(`a`) AS SELECT 1 WITH CASCADED CHECK OPTION', (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER VIEW v AS SELECT 1');
        self::assertInstanceOf(AlterViewStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithNameReplacesTheView(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER VIEW v AS SELECT 1');
        self::assertInstanceOf(AlterViewStatement::class, $statement);
        self::assertSame('ALTER VIEW `d`.`w` AS SELECT 1', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withName(new QualifiedName(['d', 'w']))));
        self::assertSame(['v'], $statement->name->parts);
    }

    public function testWithQueryReplacesTheQuery(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('ALTER VIEW v AS SELECT 1');
        $query = $binder->bind('SELECT 2, 3');
        self::assertInstanceOf(AlterViewStatement::class, $statement);
        self::assertInstanceOf(BoundQuery::class, $query);
        self::assertSame('ALTER VIEW `v` AS SELECT 2, 3', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withQuery($query)));
    }

    public function testWithCheckReplacesTheCheckOption(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER VIEW v AS SELECT 1');
        self::assertInstanceOf(AlterViewStatement::class, $statement);
        self::assertSame('ALTER VIEW `v` AS SELECT 1 WITH LOCAL CHECK OPTION', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withCheck(ViewCheck::Local)));
        self::assertSame(ViewCheck::None, $statement->check);
    }

    public function testWithPropertiesReplacesTheAlgorithmAndSecurity(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER VIEW v AS SELECT 1');
        self::assertInstanceOf(AlterViewStatement::class, $statement);
        self::assertSame('ALTER ALGORITHM = TEMPTABLE SQL SECURITY INVOKER VIEW `v` AS SELECT 1', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withProperties(new MySqlViewProperties(ViewAlgorithm::TempTable, security: ViewSecurity::Invoker))));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Inspection\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Inspection\Filter\ConditionFilter;
use SqlSemantics\Model\Query\Inspection\Filter\PatternFilter;
use SqlSemantics\Model\Statement\Inspection\Schema\ShowDatabasesStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShowDatabasesStatement::class)]
#[Medium]
final class ShowDatabasesStatementTest extends TestCase
{
    public function testResultColumnsFollowTheServerLayout(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW SCHEMAS');
        self::assertInstanceOf(ShowDatabasesStatement::class, $statement);
        self::assertNull($statement->filter);
        self::assertSame(['Database'], array_column($statement->resultColumns(), 'name'));
        self::assertSame('SHOW DATABASES', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWithFilterReplacesTheRestrictionImmutably(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("SHOW DATABASES LIKE 'app%'");
        $conditioned = $binder->bind("SHOW DATABASES WHERE `Database` <> 'x'");
        self::assertInstanceOf(ShowDatabasesStatement::class, $statement);
        self::assertInstanceOf(ShowDatabasesStatement::class, $conditioned);
        self::assertInstanceOf(PatternFilter::class, $statement->filter);
        self::assertInstanceOf(ConditionFilter::class, $conditioned->filter);
        $changed = $statement->withFilter($conditioned->filter);
        self::assertNotSame($statement, $changed);
        self::assertNotSame($statement->filter, $changed->filter);
        self::assertSame("SHOW DATABASES WHERE (`Database` <> 'x')", (new \SqlSemantics\SimpleSerializer())->serialize($changed));
        self::assertSame('SHOW DATABASES', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withFilter(null)));
        self::assertSame("SHOW DATABASES LIKE 'app%'", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWithOriginRetainsTheRestriction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SHOW DATABASES LIKE 'app%'");
        self::assertInstanceOf(ShowDatabasesStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->filter, $copy->filter);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testRejectsAnOriginFromAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        $this->expectException(InvalidStructure::class);
        new ShowDatabasesStatement(new Origin('s0', $statement->source, Dialect::PostgreSql));
    }
}

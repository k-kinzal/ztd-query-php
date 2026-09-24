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
use SqlSemantics\Model\Statement\Inspection\Schema\ShowCollationsStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShowCollationsStatement::class)]
#[Medium]
final class ShowCollationsStatementTest extends TestCase
{
    public function testResultColumnsFollowTheServerLayout(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW COLLATION');
        self::assertInstanceOf(ShowCollationsStatement::class, $statement);
        self::assertNull($statement->filter);
        self::assertSame(['Collation', 'Charset', 'Id', 'Default', 'Compiled', 'Sortlen', 'Pad_attribute'], array_column($statement->resultColumns(), 'name'));
        self::assertSame('SHOW COLLATION', $statement->toString());
    }

    public function testWithFilterReplacesTheRestrictionImmutably(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("SHOW COLLATION LIKE 'utf8%'");
        $conditioned = $binder->bind("SHOW COLLATION WHERE `Collation` <> 'x'");
        self::assertInstanceOf(ShowCollationsStatement::class, $statement);
        self::assertInstanceOf(ShowCollationsStatement::class, $conditioned);
        self::assertInstanceOf(PatternFilter::class, $statement->filter);
        self::assertInstanceOf(ConditionFilter::class, $conditioned->filter);
        $changed = $statement->withFilter($conditioned->filter);
        self::assertNotSame($statement, $changed);
        self::assertNotSame($statement->filter, $changed->filter);
        self::assertSame("SHOW COLLATION WHERE (`Collation` <> 'x')", $changed->toString());
        self::assertSame('SHOW COLLATION', $statement->withFilter(null)->toString());
        self::assertSame("SHOW COLLATION LIKE 'utf8%'", $statement->toString());
    }

    public function testWithOriginRetainsTheRestriction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SHOW COLLATION LIKE 'utf8%'");
        self::assertInstanceOf(ShowCollationsStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->filter, $copy->filter);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testRejectsAnOriginFromAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        $this->expectException(InvalidStructure::class);
        new ShowCollationsStatement(new Origin('s0', $statement->source, Dialect::PostgreSql));
    }
}

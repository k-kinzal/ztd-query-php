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
use SqlSemantics\Model\Statement\Inspection\Schema\ShowCharacterSetsStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShowCharacterSetsStatement::class)]
#[Medium]
final class ShowCharacterSetsStatementTest extends TestCase
{
    public function testResultColumnsFollowTheServerLayout(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW CHARSET');
        self::assertInstanceOf(ShowCharacterSetsStatement::class, $statement);
        self::assertNull($statement->filter);
        self::assertSame(['Charset', 'Description', 'Default collation', 'Maxlen'], array_column($statement->resultColumns(), 'name'));
        self::assertSame('SHOW CHARACTER SET', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWithFilterReplacesTheRestrictionImmutably(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("SHOW CHARACTER SET LIKE 'utf8%'");
        $conditioned = $binder->bind("SHOW CHARACTER SET WHERE `Charset` <> 'x'");
        self::assertInstanceOf(ShowCharacterSetsStatement::class, $statement);
        self::assertInstanceOf(ShowCharacterSetsStatement::class, $conditioned);
        self::assertInstanceOf(PatternFilter::class, $statement->filter);
        self::assertInstanceOf(ConditionFilter::class, $conditioned->filter);
        $changed = $statement->withFilter($conditioned->filter);
        self::assertNotSame($statement, $changed);
        self::assertNotSame($statement->filter, $changed->filter);
        self::assertSame("SHOW CHARACTER SET WHERE (`Charset` <> 'x')", (new \SqlSemantics\SimpleSerializer())->serialize($changed));
        self::assertSame('SHOW CHARACTER SET', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withFilter(null)));
        self::assertSame("SHOW CHARACTER SET LIKE 'utf8%'", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWithOriginRetainsTheRestriction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SHOW CHARACTER SET LIKE 'utf8%'");
        self::assertInstanceOf(ShowCharacterSetsStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->filter, $copy->filter);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testRejectsAnOriginFromAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        $this->expectException(InvalidStructure::class);
        new ShowCharacterSetsStatement(new Origin('s0', $statement->source, Dialect::PostgreSql));
    }
}

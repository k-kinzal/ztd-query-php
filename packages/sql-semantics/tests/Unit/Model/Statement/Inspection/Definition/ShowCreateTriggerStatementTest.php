<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Inspection\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Inspection\Definition\ShowCreateTriggerStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShowCreateTriggerStatement::class)]
#[Medium]
final class ShowCreateTriggerStatementTest extends TestCase
{
    public function testResultColumnsFollowTheServerLayout(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW CREATE TRIGGER app.`it em`');
        self::assertInstanceOf(ShowCreateTriggerStatement::class, $statement);
        self::assertSame(['app', 'it em'], $statement->trigger->parts);
        self::assertCount(7, $statement->resultColumns());
        self::assertSame('Trigger', $statement->resultColumns()[0]->name);
        self::assertSame('SHOW CREATE TRIGGER `app`.`it em`', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWithTriggerNamesAnotherObjectImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW CREATE TRIGGER item');
        self::assertInstanceOf(ShowCreateTriggerStatement::class, $statement);
        $changed = $statement->withTrigger(new QualifiedName(['other', 'renamed']));
        self::assertNotSame($statement, $changed);
        self::assertSame(['item'], $statement->trigger->parts);
        self::assertSame(['other', 'renamed'], $changed->trigger->parts);
        self::assertSame('SHOW CREATE TRIGGER `other`.`renamed`', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithOriginRetainsTheName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW CREATE TRIGGER item');
        self::assertInstanceOf(ShowCreateTriggerStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->trigger, $copy->trigger);
    }

    public function testRejectsMoreThanADatabaseQualifier(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW CREATE TRIGGER item');
        self::assertInstanceOf(ShowCreateTriggerStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new ShowCreateTriggerStatement($statement->origin, new QualifiedName(['a', 'b', 'c']));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Inspection\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Inspection\Definition\ShowCreateEventStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShowCreateEventStatement::class)]
#[Medium]
final class ShowCreateEventStatementTest extends TestCase
{
    public function testResultColumnsFollowTheServerLayout(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW CREATE EVENT app.`it em`');
        self::assertInstanceOf(ShowCreateEventStatement::class, $statement);
        self::assertSame(['app', 'it em'], $statement->event->parts);
        self::assertCount(7, $statement->resultColumns());
        self::assertSame('Event', $statement->resultColumns()[0]->name);
        self::assertSame('SHOW CREATE EVENT `app`.`it em`', $statement->toString());
    }

    public function testWithEventNamesAnotherObjectImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW CREATE EVENT item');
        self::assertInstanceOf(ShowCreateEventStatement::class, $statement);
        $changed = $statement->withEvent(new QualifiedName(['other', 'renamed']));
        self::assertNotSame($statement, $changed);
        self::assertSame(['item'], $statement->event->parts);
        self::assertSame(['other', 'renamed'], $changed->event->parts);
        self::assertSame('SHOW CREATE EVENT `other`.`renamed`', $changed->toString());
    }

    public function testWithOriginRetainsTheName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW CREATE EVENT item');
        self::assertInstanceOf(ShowCreateEventStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->event, $copy->event);
    }

    public function testRejectsMoreThanADatabaseQualifier(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW CREATE EVENT item');
        self::assertInstanceOf(ShowCreateEventStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new ShowCreateEventStatement($statement->origin, new QualifiedName(['a', 'b', 'c']));
    }
}

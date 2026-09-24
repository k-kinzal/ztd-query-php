<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Inspection\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Inspection\Definition\ShowCreateFunctionStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShowCreateFunctionStatement::class)]
#[Medium]
final class ShowCreateFunctionStatementTest extends TestCase
{
    public function testResultColumnsFollowTheServerLayout(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW CREATE FUNCTION app.`it em`');
        self::assertInstanceOf(ShowCreateFunctionStatement::class, $statement);
        self::assertSame(['app', 'it em'], $statement->function->parts);
        self::assertCount(6, $statement->resultColumns());
        self::assertSame('Function', $statement->resultColumns()[0]->name);
        self::assertSame('SHOW CREATE FUNCTION `app`.`it em`', $statement->toString());
    }

    public function testWithFunctionNamesAnotherObjectImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW CREATE FUNCTION item');
        self::assertInstanceOf(ShowCreateFunctionStatement::class, $statement);
        $changed = $statement->withFunction(new QualifiedName(['other', 'renamed']));
        self::assertNotSame($statement, $changed);
        self::assertSame(['item'], $statement->function->parts);
        self::assertSame(['other', 'renamed'], $changed->function->parts);
        self::assertSame('SHOW CREATE FUNCTION `other`.`renamed`', $changed->toString());
    }

    public function testWithOriginRetainsTheName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW CREATE FUNCTION item');
        self::assertInstanceOf(ShowCreateFunctionStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->function, $copy->function);
    }

    public function testRejectsMoreThanADatabaseQualifier(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW CREATE FUNCTION item');
        self::assertInstanceOf(ShowCreateFunctionStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new ShowCreateFunctionStatement($statement->origin, new QualifiedName(['a', 'b', 'c']));
    }
}

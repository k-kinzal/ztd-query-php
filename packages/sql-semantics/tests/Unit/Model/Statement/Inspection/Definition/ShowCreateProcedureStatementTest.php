<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Inspection\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Inspection\Definition\ShowCreateProcedureStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShowCreateProcedureStatement::class)]
#[Medium]
final class ShowCreateProcedureStatementTest extends TestCase
{
    public function testResultColumnsFollowTheServerLayout(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW CREATE PROCEDURE app.`it em`');
        self::assertInstanceOf(ShowCreateProcedureStatement::class, $statement);
        self::assertSame(['app', 'it em'], $statement->procedure->parts);
        self::assertCount(6, $statement->resultColumns());
        self::assertSame('Procedure', $statement->resultColumns()[0]->name);
        self::assertSame('SHOW CREATE PROCEDURE `app`.`it em`', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWithProcedureNamesAnotherObjectImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW CREATE PROCEDURE item');
        self::assertInstanceOf(ShowCreateProcedureStatement::class, $statement);
        $changed = $statement->withProcedure(new QualifiedName(['other', 'renamed']));
        self::assertNotSame($statement, $changed);
        self::assertSame(['item'], $statement->procedure->parts);
        self::assertSame(['other', 'renamed'], $changed->procedure->parts);
        self::assertSame('SHOW CREATE PROCEDURE `other`.`renamed`', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithOriginRetainsTheName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW CREATE PROCEDURE item');
        self::assertInstanceOf(ShowCreateProcedureStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->procedure, $copy->procedure);
    }

    public function testRejectsMoreThanADatabaseQualifier(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW CREATE PROCEDURE item');
        self::assertInstanceOf(ShowCreateProcedureStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new ShowCreateProcedureStatement($statement->origin, new QualifiedName(['a', 'b', 'c']));
    }
}

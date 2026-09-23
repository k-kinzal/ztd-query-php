<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Characteristics\RoutineAlteration;
use SqlSemantics\Model\Definition\Routine\Characteristics\RoutineSecurity;
use SqlSemantics\Model\Definition\Routine\Characteristics\SqlDataAccess;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Definition\MySql\AlterFunctionStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterFunctionStatement::class)]
#[Medium]
final class AlterFunctionStatementTest extends TestCase
{
    public function testWithNameChangesOnlyTheTargetAndQuotesItsParts(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER FUNCTION app.r NO SQL');
        self::assertInstanceOf(AlterFunctionStatement::class, $statement);
        $changed = $statement->withName(new QualifiedName(['db`name', 'new']));
        self::assertSame(['app', 'r'], $statement->name->parts);
        self::assertSame(['db`name', 'new'], $changed->name->parts);
        self::assertSame(SqlDataAccess::None, $changed->changes->dataAccess);
        self::assertStringContainsString('`db``name`.`new`', $changed->toString());
    }

    public function testWithNameRejectsTooManyQualificationLevels(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER FUNCTION r');
        self::assertInstanceOf(AlterFunctionStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withName(new QualifiedName(['a', 'b', 'c']));
    }

    public function testWithChangesReplacesAllRequestedCharacteristicsTogether(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER FUNCTION r NO SQL');
        self::assertInstanceOf(AlterFunctionStatement::class, $statement);
        $comment = Expression::literal("it's a note", Dialect::MySql);
        self::assertInstanceOf(Literal::class, $comment);
        $changed = $statement->withChanges(new RoutineAlteration('SQL', SqlDataAccess::Reads, RoutineSecurity::Invoker, $comment));
        self::assertSame(SqlDataAccess::None, $statement->changes->dataAccess);
        self::assertNull($statement->changes->comment);
        self::assertSame('SQL', $changed->changes->language);
        self::assertSame(SqlDataAccess::Reads, $changed->changes->dataAccess);
        self::assertSame(RoutineSecurity::Invoker, $changed->changes->security);
        self::assertSame($comment->text, $changed->changes->comment?->text);
        self::assertSame('ALTER FUNCTION `r`', $changed->withChanges(new RoutineAlteration())->toString());
    }

    public function testWithOriginRetainsTheCompleteRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER FUNCTION r MODIFIES SQL DATA');
        self::assertInstanceOf(AlterFunctionStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertSame($statement->name, $copy->name);
        self::assertSame($statement->changes, $copy->changes);
        self::assertNotSame($statement, $copy);
    }

    public function testWithOriginRejectsADifferentDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER FUNCTION r');
        self::assertInstanceOf(AlterFunctionStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithChangesRejectsNamedLanguagesInAnEarlierRelease(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.0.44'))->build()))->bind('ALTER FUNCTION r');
        self::assertInstanceOf(AlterFunctionStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withChanges(new RoutineAlteration(language: 'JavaScript'));
    }
}

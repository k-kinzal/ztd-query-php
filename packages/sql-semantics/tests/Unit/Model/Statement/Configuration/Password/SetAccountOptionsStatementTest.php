<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Configuration\Password;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Configuration\Password\SetAccountOptionsStatement;
use SqlSemantics\Model\Statement\Configuration\Password\SetPasswordHashStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SetAccountOptionsStatement::class)]
#[Medium]
final class SetAccountOptionsStatementTest extends TestCase
{
    public function testWithOriginRetainsTheOrderedRequests(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind("SET PASSWORD = '*one', PASSWORD FOR 'u' = '*two'");
        self::assertInstanceOf(SetAccountOptionsStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertSame($statement->operations, $copy->operations);
    }

    public function testWithOperationsValidatesANewOrderedSnapshot(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind("SET PASSWORD = '*one', PASSWORD FOR 'u' = '*two'");
        self::assertInstanceOf(SetAccountOptionsStatement::class, $statement);
        $changed = $statement->withOperations([$statement->operations[1], $statement->operations[0]]);
        self::assertInstanceOf(SetPasswordHashStatement::class, $changed->operations[0]);
        self::assertSame("'*two'", $changed->operations[0]->hash->text);
        self::assertNotSame($statement, $changed);
    }

    public function testWithOperationsRejectsCollapsingTheMixedFormToOneClause(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind("SET PASSWORD = '*one', PASSWORD FOR 'u' = '*two'");
        self::assertInstanceOf(SetAccountOptionsStatement::class, $statement);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        $statement->withOperations([$statement->operations[0]]);
    }

    public function testWithOperationsAcceptsOneVariableClauseBesideAPassword(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind("SET PASSWORD = '*one', @a = 1");
        self::assertInstanceOf(SetAccountOptionsStatement::class, $statement);
        self::assertSame("SET @`a` = 1, PASSWORD = '*one'", $statement->withOperations([$statement->operations[1], $statement->operations[0]])->toString());
    }

    public function testWithOperationsRejectsOnlyVariableClauses(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind("SET PASSWORD = '*one', @a = 1");
        self::assertInstanceOf(SetAccountOptionsStatement::class, $statement);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        $statement->withOperations([$statement->operations[1], $statement->operations[1]]);
    }

    public function testWithOperationsRejectsAVariableClauseWithTwoAssignments(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build());
        $statement = $binder->bind("SET PASSWORD = '*one', @a = 1");
        $variables = $binder->bind('SET @a = 1, @b = 2');
        self::assertInstanceOf(SetAccountOptionsStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\SetStatement::class, $variables);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        $statement->withOperations([$statement->operations[0], $variables]);
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind("SET PASSWORD = '*one', @a = 1");
        self::assertInstanceOf(SetAccountOptionsStatement::class, $statement);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        $statement->withOrigin(new \SqlSemantics\Model\Statement\Origin('other', $statement->source, Dialect::PostgreSql));
    }
}

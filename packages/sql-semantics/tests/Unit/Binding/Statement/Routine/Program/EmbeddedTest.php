<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Routine\Program\Embedded;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Body\AssignmentStatement;
use SqlSemantics\Model\Definition\Routine\Body\BlockStatement;
use SqlSemantics\Model\Definition\Routine\Body\EmbeddedStatement;
use SqlSemantics\Model\Definition\Routine\Body\SelectIntoStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Statement\Mutation\UpdateTableStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Embedded::class)]
#[Medium]
final class EmbeddedTest extends TestCase
{
    public function testBindRoutesSetSelectIntoAndOrdinaryStatements(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (n INT)'));
        $statement = $binder->bind('CREATE PROCEDURE p(a INT) BEGIN SET a = 1; SELECT MAX(n) INTO a FROM t; UPDATE t SET n = a; END');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(BlockStatement::class, $statement->body);
        [$set, $select, $update] = $statement->body->statements;
        self::assertInstanceOf(AssignmentStatement::class, $set);
        self::assertInstanceOf(SelectIntoStatement::class, $select);
        self::assertInstanceOf(EmbeddedStatement::class, $update);
        self::assertInstanceOf(UpdateTableStatement::class, $update->statement);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }
}

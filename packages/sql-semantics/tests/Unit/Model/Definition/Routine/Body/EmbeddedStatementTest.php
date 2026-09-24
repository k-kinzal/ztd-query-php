<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Body;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Body\EmbeddedStatement;
use SqlSemantics\Model\Scalar\Reference\LocalVariableReference;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Statement\Mutation\DeleteTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(EmbeddedStatement::class)]
#[Medium]
final class EmbeddedStatementTest extends TestCase
{
    public function testResolvesParametersInsideOrdinaryStatements(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (n INT)'));
        $statement = $binder->bind('CREATE PROCEDURE p(k INT) DELETE FROM t WHERE n = k');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(EmbeddedStatement::class, $statement->body);
        self::assertInstanceOf(DeleteTableStatement::class, $statement->body->statement);
        self::assertInstanceOf(LocalVariableReference::class, $statement->body->statement->where?->inputs()[1]);
        self::assertSame('CREATE PROCEDURE `p`(IN `k` integer) DELETE FROM `t` WHERE (`n` = `k`)', $statement->toString());
    }

    public function testRejectsAnotherDialectStatement(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        $this->expectException(InvalidStructure::class);
        new EmbeddedStatement($statement);
    }
}

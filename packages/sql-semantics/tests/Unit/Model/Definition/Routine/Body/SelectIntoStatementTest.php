<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Body;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\Body\BlockStatement;
use SqlSemantics\Model\Definition\Routine\Body\SelectIntoStatement;
use SqlSemantics\Model\Scalar\Reference\LocalVariableReference;
use SqlSemantics\Model\Scalar\Reference\UnresolvedVariableReference;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SelectIntoStatement::class)]
#[Medium]
final class SelectIntoStatementTest extends TestCase
{
    public function testStoresColumnsInLocalAndUserVariables(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (id INT, n INT)'));
        $statement = $binder->bind('CREATE PROCEDURE p() BEGIN DECLARE x INT; SELECT id, n INTO x, @y FROM t LIMIT 1 FOR UPDATE; END', strict: false);
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(BlockStatement::class, $statement->body);
        $into = $statement->body->statements[0];
        self::assertInstanceOf(SelectIntoStatement::class, $into);
        self::assertInstanceOf(LocalVariableReference::class, $into->targets[0]);
        self::assertInstanceOf(UnresolvedVariableReference::class, $into->targets[1]);
        self::assertSame('CREATE PROCEDURE `p`() BEGIN DECLARE `x` integer; SELECT `id` AS `id`, `n` AS `n` FROM `t` LIMIT 1 INTO `x`, @`y` FOR UPDATE; END', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement), strict: false)));
    }

    public function testRequiresOneTargetPerColumn(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('CREATE PROCEDURE p(a INT) SELECT 1 INTO a');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(SelectIntoStatement::class, $statement->body);
        $this->expectException(InvalidStructure::class);
        new SelectIntoStatement($statement->body->query, [...$statement->body->targets, ...$statement->body->targets]);
    }

    public function testDiagnosesAnUndeclaredTarget(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramObject->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() SELECT 1 INTO missing', strict: false);
    }
}

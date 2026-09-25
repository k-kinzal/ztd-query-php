<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Body;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Body\WhileStatement;
use SqlSemantics\Model\Scalar\Reference\LocalVariableReference;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(WhileStatement::class)]
#[Medium]
final class WhileStatementTest extends TestCase
{
    public function testTestsTheConditionBeforeEachPass(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('CREATE PROCEDURE p(a INT) w: WHILE a DO SET a = a - 1; END WHILE w');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(WhileStatement::class, $statement->body);
        self::assertInstanceOf(LocalVariableReference::class, $statement->body->condition);
        self::assertSame('CREATE PROCEDURE `p`(IN `a` integer) `w` : WHILE `a` DO SET `a` = (`a` - 1); END WHILE `w`', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testRequiresStatements(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p(a INT) WHILE a DO DO 1; END WHILE');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(WhileStatement::class, $statement->body);
        $this->expectException(InvalidStructure::class);
        new WhileStatement(null, $statement->body->condition, []);
    }

    public function testRejectsAnEmptyLabel(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p(a INT) WHILE a DO DO 1; END WHILE');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(WhileStatement::class, $statement->body);
        $this->expectException(InvalidStructure::class);
        new WhileStatement('', $statement->body->condition, $statement->body->statements);
    }
}

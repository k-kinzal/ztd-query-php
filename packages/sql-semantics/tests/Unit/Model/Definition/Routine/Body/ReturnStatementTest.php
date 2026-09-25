<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Body;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\Definition\Routine\Body\ReturnStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateFunctionStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ReturnStatement::class)]
#[Medium]
final class ReturnStatementTest extends TestCase
{
    public function testReturnsTheFunctionResult(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('CREATE FUNCTION f(a INT) RETURNS INT RETURN a');
        self::assertInstanceOf(CreateFunctionStatement::class, $statement);
        self::assertInstanceOf(ReturnStatement::class, $statement->body);
        self::assertSame('CREATE FUNCTION `f`(`a` integer) RETURNS integer RETURN `a`', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testRejectsAnotherDialectValue(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(BoundQuery::class, $query);
        $this->expectException(InvalidStructure::class);
        new ReturnStatement($query->resultColumns()[0]->expression);
    }

    public function testDiagnosesReturnInAProcedure(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramStatement->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() RETURN 1', strict: false);
    }
}

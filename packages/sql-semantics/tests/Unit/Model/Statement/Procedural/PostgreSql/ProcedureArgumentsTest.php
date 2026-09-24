<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Procedural\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Procedural\PostgreSql\CallProcedureStatement;
use SqlSemantics\Model\Statement\Procedural\PostgreSql\ProcedureArgument;
use SqlSemantics\Model\Statement\Procedural\PostgreSql\ProcedureArguments;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ProcedureArguments::class)]
#[Medium]
final class ProcedureArgumentsTest extends TestCase
{
    public function testValidateAcceptsPositionalThenNamedArguments(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CALL p(1, a => 2, b => 3)');
        self::assertInstanceOf(CallProcedureStatement::class, $statement);
        ProcedureArguments::validate($statement->arguments);
        self::assertCount(3, $statement->arguments);
    }

    public function testValidateRejectsAVariadicArgumentBeforeTheLast(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CALL p(1, 2)');
        self::assertInstanceOf(CallProcedureStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        ProcedureArguments::validate([new ProcedureArgument($statement->arguments[0]->value, null, true), $statement->arguments[1]]);
    }

    public function testValidateRejectsARepeatedName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CALL p(a => 1, b => 2)');
        self::assertInstanceOf(CallProcedureStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        ProcedureArguments::validate([$statement->arguments[0], new ProcedureArgument($statement->arguments[1]->value, 'a')]);
    }

    public function testValidateRejectsAPositionalArgumentAfterANamedOne(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CALL p(1, a => 2)');
        self::assertInstanceOf(CallProcedureStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        ProcedureArguments::validate([$statement->arguments[1], $statement->arguments[0]]);
    }

    public function testValidateRejectsAnotherDialect(): void
    {
        $select = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\ResultStatement::class, $select);
        $value = $select->resultColumns()[0]->expression;
        $this->expectException(InvalidStructure::class);
        ProcedureArguments::validate([new ProcedureArgument($value)]);
    }
}

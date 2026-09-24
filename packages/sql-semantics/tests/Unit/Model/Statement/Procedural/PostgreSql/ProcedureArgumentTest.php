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
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ProcedureArgument::class)]
#[Medium]
final class ProcedureArgumentTest extends TestCase
{
    public function testArgumentsKeepNotationAndVariadicMarker(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CALL p(1, VARIADIC a => ARRAY[2])');
        self::assertInstanceOf(CallProcedureStatement::class, $statement);
        self::assertNull($statement->arguments[0]->name);
        self::assertFalse($statement->arguments[0]->variadic);
        self::assertSame('a', $statement->arguments[1]->name);
        self::assertTrue($statement->arguments[1]->variadic);
    }

    public function testRejectsAnEmptyParameterName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CALL p(1)');
        self::assertInstanceOf(CallProcedureStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new ProcedureArgument($statement->arguments[0]->value, '');
    }
}

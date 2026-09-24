<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Stored;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\ParameterMode;
use SqlSemantics\Model\Definition\Routine\Stored\DeclaredDomain;
use SqlSemantics\Model\Definition\Routine\Stored\ProcedureParameter;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(ProcedureParameter::class)]
#[Medium]
final class ProcedureParameterTest extends TestCase
{
    public function testDefaultsAnOmittedDirectionToIn(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p(a INT, OUT b INT, INOUT c INT) BEGIN END');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertSame([ParameterMode::Input, ParameterMode::Output, ParameterMode::InputOutput], array_column($statement->parameters, 'mode'));
        self::assertSame('CREATE PROCEDURE `p`(IN `a` integer, OUT `b` integer, INOUT `c` integer) BEGIN END', $statement->toString());
    }

    public function testRejectsAVariadicParameter(): void
    {
        $this->expectException(InvalidStructure::class);
        new ProcedureParameter('a', new DeclaredDomain(TypeDescriptor::builtin(Dialect::MySql, 'integer')), ParameterMode::Variadic);
    }

    public function testRejectsAnUnnamedParameter(): void
    {
        $this->expectException(InvalidStructure::class);
        new ProcedureParameter('', new DeclaredDomain(TypeDescriptor::builtin(Dialect::MySql, 'integer')));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Body\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\Body\BlockStatement;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\VariableDeclaration;
use SqlSemantics\Model\Definition\Routine\Stored\DeclaredDomain;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(VariableDeclaration::class)]
#[Medium]
final class VariableDeclarationTest extends TestCase
{
    public function testVariablesShareTheDeclaredDomain(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('CREATE PROCEDURE p() BEGIN DECLARE a, b DECIMAL(10, 2) DEFAULT a; END');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(BlockStatement::class, $statement->body);
        $declaration = $statement->body->declarations[0];
        self::assertInstanceOf(VariableDeclaration::class, $declaration);
        self::assertSame(['a', 'b'], array_column($declaration->variables(), 'name'));
        self::assertSame($declaration->domain, $declaration->variables()[1]->domain);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testRejectsRepeatedNames(): void
    {
        $this->expectException(InvalidStructure::class);
        new VariableDeclaration(['a', 'A'], new DeclaredDomain(TypeDescriptor::builtin(Dialect::MySql, 'integer')));
    }

    public function testDiagnosesARepeatedName(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramDeclaration->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() BEGIN DECLARE a, a INT; END', strict: false);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Stored;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Stored\DeclaredDomain;
use SqlSemantics\Model\Definition\Routine\Stored\FunctionParameter;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateFunctionStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(FunctionParameter::class)]
#[Medium]
final class FunctionParameterTest extends TestCase
{
    public function testRetainsNamesAndDomainsInOrder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE FUNCTION f(a INT, b CHAR(2)) RETURNS INT RETURN a');
        self::assertInstanceOf(CreateFunctionStatement::class, $statement);
        self::assertSame(['a', 'b'], array_column($statement->parameters, 'name'));
        self::assertSame('char', $statement->parameters[1]->domain->type->name);
    }

    public function testRejectsAnUnnamedParameter(): void
    {
        $this->expectException(InvalidStructure::class);
        new FunctionParameter('', new DeclaredDomain(TypeDescriptor::builtin(Dialect::MySql, 'integer')));
    }
}

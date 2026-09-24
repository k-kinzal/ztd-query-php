<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Functions;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Functions\Registration;
use SqlSemantics\Schema\FunctionSignature;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(Registration::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class RegistrationTest extends TestCase
{
    public function testCheckAcceptsASignatureInTheSchemaDialect(): void
    {
        $function = new FunctionSignature('f', [TypeDescriptor::builtin(Dialect::MySql, 'integer')], TypeDescriptor::builtin(Dialect::MySql, 'text'));
        Registration::check(Dialect::MySql, $function);
        self::assertInstanceOf(TypeDescriptor::class, $function->returnType);
        self::assertSame(Dialect::MySql, $function->returnType->dialect);
    }

    public function testCheckRejectsParametersFromAnotherDialect(): void
    {
        $function = new FunctionSignature('f', [TypeDescriptor::builtin(Dialect::MySql, 'integer')], TypeDescriptor::builtin(Dialect::MySql, 'text'));
        $this->expectException(InvalidStructure::class);
        Registration::check(Dialect::Sqlite, $function);
    }

    public function testCheckRejectsAReturnTypeFromAnotherDialect(): void
    {
        $function = new FunctionSignature('f', null, TypeDescriptor::builtin(Dialect::MySql, 'text'));
        $this->expectException(InvalidStructure::class);
        Registration::check(Dialect::PostgreSql, $function);
    }
}

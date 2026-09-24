<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Function;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Function\DeclaredFunction;
use SqlSemantics\Schema\FunctionSignature;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(DeclaredFunction::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class DeclaredFunctionTest extends TestCase
{
    public function testNameIncludesTheRegisteredSchema(): void
    {
        $integer = TypeDescriptor::builtin(Dialect::PostgreSql, 'integer');
        $signature = new FunctionSignature('row_total', [], $integer, Nullability::NotNull, aggregate: true, schema: 'app');
        $function = new DeclaredFunction($signature);
        self::assertSame(['app', 'row_total'], $function->name()->parts);
    }

    public function testNameOmitsAMissingSchema(): void
    {
        $integer = TypeDescriptor::builtin(Dialect::MySql, 'integer');
        $function = new DeclaredFunction(new FunctionSignature('total', [$integer], $integer));
        self::assertSame(['total'], $function->name()->parts);
    }

    public function testRetainsTheSelectedSignature(): void
    {
        $integer = TypeDescriptor::builtin(Dialect::Sqlite, 'integer');
        $signature = new FunctionSignature('total', [$integer], $integer);
        $function = new DeclaredFunction($signature);
        self::assertSame($signature, $function->signature);
        self::assertNotSame($function->name(), $function->name());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\FunctionModel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\BuiltinCallModel;
use SqlCatalog\Analysis\FunctionModel\Registry;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\LiteralTerm;

#[CoversClass(Registry::class)]
#[UsesClass(BuiltinCallModel::class)]
#[UsesClass(Domain::class)]
#[UsesClass(LiteralTerm::class)]
final class RegistryTest extends TestCase
{
    public function testWithBuiltinsRetainsStandardModels(): void
    {
        $models = Registry::withBuiltins();
        self::assertSame('USERS', $models->evaluate('strtoupper', [Domain::literal('users')])?->soleLiteral()?->value);
        self::assertNull($models->evaluate('array_fill', []));
    }

    public function testRegisterOverridesAndCanDeferToEarlierModels(): void
    {
        $models = Registry::withBuiltins();
        $models->register('STRTOUPPER', static fn (array $arguments): ?Domain => ($arguments[0] ?? Domain::unknown())->soleLiteral()?->value === 'special' ? Domain::literal('override') : null);
        self::assertSame('override', $models->evaluate('strtoupper', [Domain::literal('special')])?->soleLiteral()?->value);
        self::assertSame('OTHER', $models->evaluate('strtoupper', [Domain::literal('other')])?->soleLiteral()?->value);
    }

    public function testSupportsKeepsNamespacedFunctionsSeparate(): void
    {
        $models = new Registry();
        $models->register('\\App\\table', static fn (array $arguments): Domain => Domain::literal('users'));
        self::assertTrue($models->supports('app\\TABLE'));
        self::assertFalse($models->supports('table'));
        self::assertFalse($models->supports('Other\\table'));
    }

    public function testEvaluateReturnsNullWhenEveryModelDeclines(): void
    {
        $models = new Registry();
        $models->register('table', static fn (array $arguments): ?Domain => null);
        self::assertNull($models->evaluate('table', []));
        self::assertNull($models->evaluate('unknown', []));
    }

    public function testNormalizeKeepsTheNamespace(): void
    {
        self::assertSame('app\\implode', (new Registry())->normalize('\\App\\IMPlODE'));
    }
}

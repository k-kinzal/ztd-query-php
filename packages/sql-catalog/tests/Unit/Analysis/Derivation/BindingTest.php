<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Derivation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\Derivation\Binding;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\Environment;
use SqlCatalog\Evaluation\LiteralTerm;

#[CoversClass(Binding::class)]
#[UsesClass(Domain::class)]
#[UsesClass(Environment::class)]
#[UsesClass(LiteralTerm::class)]
final class BindingTest extends TestCase
{
    public function testABindingHoldsTheNamesAndTheWayIn(): void
    {
        $binding = new Binding(new Environment(['id' => Domain::literal(7)]), ['caller', 'f'], true, true);

        self::assertSame(7, $binding->environment->read('id')->soleLiteral()?->value);
        self::assertSame(['caller', 'f'], $binding->through);
        self::assertTrue($binding->truncated);
        self::assertTrue($binding->combined);
    }

    public function testABindingIsNeitherCutShortNorPairedUnlessItSaysSo(): void
    {
        $binding = new Binding(new Environment(), []);

        self::assertFalse($binding->truncated);
        self::assertFalse($binding->combined);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis\Derivation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Analysis\Derivation\Binding;
use SqlCatalog\Core\Evaluation\Domain;
use SqlCatalog\Core\Evaluation\Environment;
use SqlCatalog\Core\Evaluation\LiteralTerm;

#[CoversClass(Binding::class)]
#[UsesClass(Domain::class)]
#[UsesClass(Environment::class)]
#[UsesClass(LiteralTerm::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Effect\WriteEffects::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Effect\ReferenceEffects::class)]
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

<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Definition\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Rules\Definition\Query\DerivedAffinity;
use SqlSemantics\Platform\Sqlite\Statement\Type\Affinity;

#[CoversClass(DerivedAffinity::class)]
#[Small]
final class DerivedAffinityTest extends TestCase
{
    public function testNumericHoldsForTheNumericAffinitiesAndTheFlexibleOne(): void
    {
        self::assertTrue((new DerivedAffinity(Affinity::Numeric))->numeric());
        self::assertTrue((new DerivedAffinity(Affinity::Integer))->numeric());
        self::assertTrue((new DerivedAffinity(Affinity::Real))->numeric());
        self::assertTrue((new DerivedAffinity(Affinity::Numeric, true))->numeric());
        self::assertFalse((new DerivedAffinity(Affinity::Text))->numeric());
        self::assertFalse((new DerivedAffinity(Affinity::Blob))->numeric());
        self::assertFalse((new DerivedAffinity())->numeric());
    }

    public function testTypedHoldsForEveryAffinityButBlobAndNone(): void
    {
        self::assertTrue((new DerivedAffinity(Affinity::Text))->typed());
        self::assertTrue((new DerivedAffinity(Affinity::Real))->typed());
        self::assertFalse((new DerivedAffinity(Affinity::Blob))->typed());
        self::assertFalse((new DerivedAffinity())->typed());
        self::assertFalse((new DerivedAffinity(null, false, false))->typed());
    }
}

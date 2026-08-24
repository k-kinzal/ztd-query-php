<?php

declare(strict_types=1);

namespace Tests\Unit\Fixture;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Fixture\RowSpec;
use SqlFixture\Fixture\TableOverrides;

#[CoversClass(RowSpec::class)]
#[UsesClass(TableOverrides::class)]
final class RowSpecTest extends TestCase
{
    #[Test]
    public function anUnspecifiedTableLeavesTheCountFree(): void
    {
        $spec = RowSpec::unspecified();

        self::assertNull($spec->count);
        self::assertSame([], $spec->overridesFor(0));
    }

    #[Test]
    public function anIntegerIsACountWithNothingOverridden(): void
    {
        $spec = RowSpec::from('order_detail', 3);

        self::assertSame(3, $spec->count);
        self::assertSame([], $spec->overridesFor(1));
    }

    #[Test]
    public function anEmptyArrayMeansNoRows(): void
    {
        self::assertSame(0, RowSpec::from('order_detail', [])->count);
    }

    #[Test]
    public function oneSetOfValuesAppliesToEveryRow(): void
    {
        $spec = RowSpec::from('order_detail', ['quantity' => 2]);

        self::assertNull($spec->count);
        self::assertSame(['quantity' => 2], $spec->overridesFor(0));
        self::assertSame(['quantity' => 2], $spec->overridesFor(7));
    }

    #[Test]
    public function aListGivesOneEntryPerRow(): void
    {
        $spec = RowSpec::from('order_detail', [['quantity' => 1], ['quantity' => 2]]);

        self::assertSame(2, $spec->count);
        self::assertSame(['quantity' => 1], $spec->overridesFor(0));
        self::assertSame(['quantity' => 2], $spec->overridesFor(1));
        self::assertSame([], $spec->overridesFor(2));
    }

    #[Test]
    public function tableOverridesApplyToEveryRow(): void
    {
        $spec = RowSpec::from('order', TableOverrides::of(['status' => 'paid']));

        self::assertNull($spec->count);
        self::assertSame(['status' => 'paid'], $spec->overridesFor(3));
    }

    #[Test]
    public function aListOfTableOverridesGivesOneEntryPerRow(): void
    {
        $spec = RowSpec::from('order_detail', [
            TableOverrides::of(['quantity' => 1]),
            TableOverrides::of(['quantity' => 2]),
        ]);

        self::assertSame(2, $spec->count);
        self::assertSame(['quantity' => 2], $spec->overridesFor(1));
    }

    #[Test]
    public function aNegativeCountIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be negative');

        RowSpec::from('order_detail', -1);
    }

    #[Test]
    public function aListOfScalarsIsReadAsColumnValuesNotRows(): void
    {
        $spec = RowSpec::from('order_detail', ['a', 'b']);

        self::assertNull($spec->count);
        self::assertSame(['a', 'b'], $spec->overridesFor(0));
    }

    #[Test]
    public function zeroIsAValidCount(): void
    {
        self::assertSame(0, RowSpec::from('order_detail', 0)->count);
    }

    #[Test]
    public function aKeyedArrayOfArraysIsStillOneSetOfValues(): void
    {
        $spec = RowSpec::from('order_detail', ['payload' => ['a' => 1]]);

        self::assertNull($spec->count);
        self::assertSame(['payload' => ['a' => 1]], $spec->overridesFor(0));
    }
}

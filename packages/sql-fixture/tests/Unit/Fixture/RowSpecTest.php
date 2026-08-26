<?php

declare(strict_types=1);

namespace Tests\Unit\Fixture;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Fixture\RowSpec;
use SqlFixture\Fixture\TableOverrides;
use SqlFixture\InvalidOverrideException;
use stdClass;

#[CoversClass(RowSpec::class)]
#[UsesClass(TableOverrides::class)]
#[UsesClass(InvalidOverrideException::class)]
final class RowSpecTest extends TestCase
{
    #[Test]
    public function testUnspecifiedAnUnspecifiedTableLeavesTheCountFree(): void
    {
        $spec = RowSpec::unspecified();

        self::assertNull($spec->count);
        self::assertSame([], $spec->overridesFor(0));
    }

    #[Test]
    public function testFromAnIntegerIsACountWithNothingOverridden(): void
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
    public function testOverridesForOneSetOfValuesAppliesToEveryRow(): void
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

    #[Test]
    public function testAsRowsReadsAListOfArraysAsOneEntryPerRow(): void
    {
        self::assertSame(
            [['status' => 'paid'], ['status' => 'shipped']],
            RowSpec::asRows([['status' => 'paid'], ['status' => 'shipped']])
        );
    }

    #[Test]
    public function testAsRowsReadsATableOverridesEntryAsTheRowItDescribes(): void
    {
        self::assertSame(
            [['status' => 'paid']],
            RowSpec::asRows([TableOverrides::of(['status' => 'paid'])])
        );
    }

    #[Test]
    public function testAsRowsIsNullWhenTheRowsWereDescribedTogether(): void
    {
        self::assertNull(RowSpec::asRows(['status' => 'paid']));
        self::assertNull(RowSpec::asRows(['paid', 'shipped']));
    }

    #[Test]
    public function testForTablesReadsWhatWasWrittenForEveryTableNamed(): void
    {
        $specs = RowSpec::forTables(['order' => 2, 'customer' => ['name' => 'Ada']]);

        self::assertSame(['order', 'customer'], array_keys($specs));
        self::assertSame(2, $specs['order']->count);
        self::assertSame(['name' => 'Ada'], $specs['customer']->overridesFor(0));
    }

    #[Test]
    public function testForTablesRefusesANegativeRowCount(): void
    {
        $this->expectException(InvalidOverrideException::class);

        RowSpec::forTables(['order' => -1]);
    }

    #[Test]
    public function testAsRowKeepsEveryColumnTheCallerNamed(): void
    {
        self::assertSame(
            ['id' => 1, 'name' => 'Ada', 'rate' => 0.5, 'active' => true, 'note' => null],
            RowSpec::asRow(['id' => 1, 'name' => 'Ada', 'rate' => 0.5, 'active' => true, 'note' => null])
        );
    }

    #[Test]
    public function testAsRowRefusesAValueNoColumnCouldHold(): void
    {
        $this->expectException(InvalidOverrideException::class);

        RowSpec::asRow(['payload' => new stdClass()]);
    }

    #[Test]
    public function testAsOverrideKeepsAScalarOrNullAsItWasWritten(): void
    {
        self::assertSame(7, RowSpec::asOverride('id', 7));
        self::assertNull(RowSpec::asOverride('note', null));
        self::assertFalse(RowSpec::asOverride('active', false));
    }

    #[Test]
    public function testAsOverrideKeepsAnArrayOfScalarsSoAJsonColumnCanBeWrittenOut(): void
    {
        self::assertSame(['a' => 1, 'b' => null], RowSpec::asOverride('payload', ['a' => 1, 'b' => null]));
    }

    #[Test]
    public function testAsOverrideRefusesAnArrayOfArrays(): void
    {
        $this->expectException(InvalidOverrideException::class);

        RowSpec::asOverride('payload', [['a' => 1]]);
    }

    #[Test]
    public function testAsOverrideNamesTheColumnItRefused(): void
    {
        $this->expectExceptionMessage('payload');

        RowSpec::asOverride('payload', new stdClass());
    }
}

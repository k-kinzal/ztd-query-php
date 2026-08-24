<?php

declare(strict_types=1);

namespace Tests\Unit\Fixture;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlFixture\Fixture\TableOverrides;

#[CoversClass(TableOverrides::class)]
final class TableOverridesTest extends TestCase
{
    #[Test]
    public function keepsTheValuesGiven(): void
    {
        self::assertSame(
            ['status' => 'paid', 'total' => 10],
            TableOverrides::of(['status' => 'paid', 'total' => 10])->toArray()
        );
    }

    #[Test]
    public function dropsArgumentsLeftUnset(): void
    {
        self::assertSame(
            ['status' => 'paid'],
            TableOverrides::of(['status' => 'paid', 'total' => null])->toArray()
        );
    }

    #[Test]
    public function keepsFalseAndZeroWhichAreRealValues(): void
    {
        self::assertSame(
            ['flag' => false, 'total' => 0, 'name' => ''],
            TableOverrides::of(['flag' => false, 'total' => 0, 'name' => ''])->toArray()
        );
    }

    #[Test]
    public function withNullSetsAColumnDeliberately(): void
    {
        self::assertSame(
            ['status' => 'paid', 'note' => null],
            TableOverrides::of(['status' => 'paid'])->withNull('note')->toArray()
        );
    }

    #[Test]
    public function withNullTakesSeveralColumns(): void
    {
        self::assertSame(
            ['a' => null, 'b' => null],
            TableOverrides::of()->withNull('a', 'b')->toArray()
        );
    }

    #[Test]
    public function withNullReturnsANewInstance(): void
    {
        $base = TableOverrides::of(['status' => 'paid']);

        self::assertNotSame($base, $base->withNull('note'));
        self::assertSame(['status' => 'paid'], $base->toArray());
    }

    #[Test]
    public function anEmptySetOfOverridesIsEmpty(): void
    {
        self::assertSame([], TableOverrides::of()->toArray());
    }

    #[Test]
    public function withNullKeepsEveryColumnAlreadyMarked(): void
    {
        self::assertSame(
            ['a' => null, 'b' => null],
            TableOverrides::of()->withNull('a')->withNull('b')->toArray()
        );
    }

    #[Test]
    public function withNullReindexesColumnsSpreadFromAKeyedArray(): void
    {
        self::assertSame(
            ['a' => null, 'b' => null],
            TableOverrides::of()->withNull(...['first' => 'a', 'second' => 'b'])->toArray()
        );
    }
}

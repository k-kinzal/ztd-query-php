<?php

declare(strict_types=1);

namespace Tests\Unit\Plan;

use MySqlMemory\Plan\ColumnOrigin;
use MySqlMemory\Result\ColumnFlag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ColumnOrigin::class)]
#[Small]
final class ColumnOriginTest extends TestCase
{
    public function testUnkeyedClearsThePrimaryUniqueAndMultipleKeyFlags(): void
    {
        $flags = ColumnFlag::NotNull->value | ColumnFlag::PrimaryKey->value | ColumnFlag::UniqueKey->value | ColumnFlag::MultipleKey->value | ColumnFlag::NoDefaultValue->value;

        $origin = (new ColumnOrigin('d', 'x', 't', 'a', $flags))->unkeyed();

        self::assertSame(['d', 'x', 't', 'a', ColumnFlag::NotNull->value | ColumnFlag::NoDefaultValue->value], [$origin->schema, $origin->table, $origin->originalTable, $origin->column, $origin->flags]);
    }

    public function testUnkeyedKeepsAnOriginWithoutKeyFlags(): void
    {
        $origin = (new ColumnOrigin('', '', '', '', ColumnFlag::Blob->value))->unkeyed();

        self::assertSame(ColumnFlag::Blob->value, $origin->flags);
    }

    public function testUnkeyedKeepsWhetherTheFlagsAreExact(): void
    {
        self::assertTrue((new ColumnOrigin('', 't', 't', 'x', 2, true))->unkeyed()->exact);
    }
}

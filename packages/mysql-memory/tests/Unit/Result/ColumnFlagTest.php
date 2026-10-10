<?php

declare(strict_types=1);

namespace Tests\Unit\Result;

use MySqlMemory\Result\ColumnFlag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ColumnFlag::class)]
#[Small]
final class ColumnFlagTest extends TestCase
{
    public function testCasesHoldTheBitsOfTheProtocol(): void
    {
        self::assertSame(
            [
                'NotNull' => 1,
                'PrimaryKey' => 2,
                'UniqueKey' => 4,
                'MultipleKey' => 8,
                'Blob' => 16,
                'Unsigned' => 32,
                'ZeroFill' => 64,
                'Binary' => 128,
                'Enum' => 256,
                'AutoIncrement' => 512,
                'Timestamp' => 1024,
                'Set' => 2048,
                'NoDefaultValue' => 4096,
                'OnUpdateNow' => 8192,
                'Numeric' => 32768,
            ],
            array_combine(array_map(static fn (ColumnFlag $flag): string => $flag->name, ColumnFlag::cases()), array_map(static fn (ColumnFlag $flag): int => $flag->value, ColumnFlag::cases())),
        );
    }

    public function testCasesCombineIntoTheFlagsOfAColumn(): void
    {
        self::assertSame(33, ColumnFlag::NotNull->value | ColumnFlag::Unsigned->value);
        self::assertSame(ColumnFlag::Unsigned, ColumnFlag::from(32));
    }
}

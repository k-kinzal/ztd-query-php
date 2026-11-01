<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Catalog\ColumnDimensions;

#[CoversClass(ColumnDimensions::class)]
#[Small]
final class ColumnDimensionsTest extends TestCase
{
    public function testColumnDimensionsRetainsDimensionsIndependentlyOfDisplayText(): void
    {
        $type = new ColumnDimensions('decimal', precision: 12, scale: 3);
        self::assertSame(['decimal', null, null, 12, 3, null], [$type->name, $type->characters, $type->octets, $type->precision, $type->scale, $type->fraction]);
    }

    #[DataProvider('providerInvalidDimensions')]
    public function testColumnDimensionsRefusesInconsistentDimensions(string $name, ?int $characters, ?int $octets, ?int $precision, ?int $scale, ?int $fraction, string $message): void
    {
        $this->expectExceptionMessage($message);
        new ColumnDimensions($name, $characters, $octets, $precision, $scale, $fraction);
    }

    /**
     * @return iterable<string, array{string, int|null, int|null, int|null, int|null, int|null, string}>
     */
    public static function providerInvalidDimensions(): iterable
    {
        yield 'display syntax is not a type name' => ['varchar(8)', 8, 8, null, null, null, 'A listed type has a canonical data type name.'];
        yield 'negative length' => ['varchar', -1, 1, null, null, null, 'A listed type dimension is nonnegative or absent.'];
        yield 'missing byte length' => ['varchar', 8, null, null, null, null, 'Character and octet lengths are both present or both absent.'];
        yield 'short byte length' => ['varchar', 8, 7, null, null, null, 'Octet length is at least character length.'];
        yield 'missing precision' => ['decimal', null, null, null, 2, null, 'A numeric scale has sufficient precision.'];
        yield 'excess scale' => ['decimal', null, null, 2, 3, null, 'A numeric scale has sufficient precision.'];
        yield 'excess fraction' => ['datetime', null, null, null, null, 7, 'Fractional seconds precision is at most six.'];
    }
}

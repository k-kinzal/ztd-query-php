<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\NativeColumnTypeProvider;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(ColumnType::class)]
final class ColumnTypeTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $type = new ColumnType(ColumnTypeFamily::INTEGER, 'INT');

        self::assertSame(ColumnTypeFamily::INTEGER, $type->family);
        self::assertSame('INT', $type->nativeType);
    }

    public function testDifferentFamilies(): void
    {
        $text = new ColumnType(ColumnTypeFamily::TEXT, 'TEXT');
        $bool = new ColumnType(ColumnTypeFamily::BOOLEAN, 'BOOLEAN');

        self::assertSame(ColumnTypeFamily::TEXT, $text->family);
        self::assertSame(ColumnTypeFamily::BOOLEAN, $bool->family);
    }

    #[DataProviderExternal(NativeColumnTypeProvider::class, 'provide')]
    public function testCreatesTypeFamiliesFromDriverNativeNames(
        string $nativeType,
        ColumnTypeFamily $expectedFamily,
    ): void {
        self::assertSame($expectedFamily, ColumnType::fromNativeType($nativeType)->family);
    }

    public function testPreservesNativeTypeForDialectRendering(): void
    {
        $type = ColumnType::fromNativeType('numeric(10, 2)');

        self::assertSame('numeric(10, 2)', $type->nativeType);
    }

}

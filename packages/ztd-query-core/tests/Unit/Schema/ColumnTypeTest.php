<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;
use PHPUnit\Framework\Attributes\CoversClass;

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
}

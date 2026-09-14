<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(ColumnTypeFamily::class)]
final class ColumnTypeFamilyTest extends TestCase
{
    public function testAllCasesExist(): void
    {
        $cases = ColumnTypeFamily::cases();

        self::assertCount(14, $cases);
    }

    public function testCaseValues(): void
    {
        self::assertSame('integer', ColumnTypeFamily::INTEGER->value);
        self::assertSame('text', ColumnTypeFamily::TEXT->value);
        self::assertSame('unknown', ColumnTypeFamily::UNKNOWN->value);
    }
}

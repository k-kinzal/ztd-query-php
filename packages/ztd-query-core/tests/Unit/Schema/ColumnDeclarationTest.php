<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(ColumnDeclaration::class)]
final class ColumnDeclarationTest extends TestCase
{
    public function testKeepsTheFamilyAndTheNameTheDatabaseGivesTheType(): void
    {
        $type = new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'INT');

        self::assertSame(ColumnTypeFamily::INTEGER, $type->family);
        self::assertSame('INT', $type->nativeType);
    }

    public function testDifferentFamilies(): void
    {
        $text = new ColumnDeclaration(ColumnTypeFamily::TEXT, 'TEXT');
        $bool = new ColumnDeclaration(ColumnTypeFamily::BOOLEAN, 'BOOLEAN');

        self::assertSame(ColumnTypeFamily::TEXT, $text->family);
        self::assertSame(ColumnTypeFamily::BOOLEAN, $bool->family);
    }

}

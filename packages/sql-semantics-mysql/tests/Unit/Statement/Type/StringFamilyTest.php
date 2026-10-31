<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\StringFamily;

#[CoversClass(StringFamily::class)]
#[Small]
final class StringFamilyTest extends TestCase
{
    public function testNameReportsAnUnsizedExpressionFamily(): void
    {
        self::assertSame('VARCHAR', (new StringFamily())->name());
        self::assertSame('VARBINARY', (new StringFamily(true))->name());
    }

    public function testNamePreservesANationalFamily(): void
    {
        self::assertSame('NVARCHAR', (new StringFamily(national: true))->name());
    }

    public function testBinaryFamilyRefusesCharacterSetAttributes(): void
    {
        $this->expectExceptionMessage('A binary string family has no character set attributes.');
        new StringFamily(true, true);
    }

    public function testNationalFamilyRefusesANamedCharset(): void
    {
        $this->expectExceptionMessage('A national string family takes the BINARY attribute alone.');
        new StringFamily(national: true, charset: new \SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm::Ascii));
    }

}

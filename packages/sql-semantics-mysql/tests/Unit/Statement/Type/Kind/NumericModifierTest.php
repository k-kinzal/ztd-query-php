<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Kind;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;

#[CoversClass(NumericModifier::class)]
#[Small]
final class NumericModifierTest extends TestCase
{
    public function testCasesSpellTheKeywordOfEveryNumericAttribute(): void
    {
        self::assertSame(['Signed', 'Unsigned', 'Zerofill'], array_column(NumericModifier::cases(), 'name'));
        self::assertSame(['SIGNED', 'UNSIGNED', 'ZEROFILL'], array_column(NumericModifier::cases(), 'value'));
    }
}

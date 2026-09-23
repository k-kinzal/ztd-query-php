<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Schema\Storage\ImpliedSetting;

#[CoversClass(ImpliedSetting::class)]
final class ImpliedSettingTest extends TestCase
{
    public function testNamingAnOptionWithoutAValueEnablesIt(): void
    {
        self::assertSame(['enabled'], array_column(ImpliedSetting::cases(), 'value'));
    }

}

<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Contract\GrammarRelease;
use SqlSemantics\Statement\Contract\LanguageProfile;
use SqlSemantics\Statement\Contract\ParameterStyle;

#[CoversClass(ParameterStyle::class)]
#[Small]
final class ParameterStyleTest extends TestCase
{
    public function testTheNamedExtensionIsNotCompatibleWithNativeMarkers(): void
    {
        $native = new LanguageProfile(GrammarRelease::MySql847);
        $named = new LanguageProfile(GrammarRelease::MySql847, parameters: ParameterStyle::Named);
        self::assertFalse($native->compatibleWith($named));
    }
}

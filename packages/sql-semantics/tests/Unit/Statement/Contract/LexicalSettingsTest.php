<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Contract\LexicalSettings;
use SqlSemantics\Statement\Validation\Failure\InvalidConstruction;

#[CoversClass(LexicalSettings::class)]
#[Small]
final class LexicalSettingsTest extends TestCase
{
    public function testEqualsUsesEffectiveFlagsNotInputOrderOrDuplicates(): void
    {
        $first = new LexicalSettings('ANSI_QUOTES,NO_BACKSLASH_ESCAPES');
        $second = new LexicalSettings('NO_BACKSLASH_ESCAPES,ANSI_QUOTES,ANSI_QUOTES');
        self::assertTrue($first->equals($second));
        self::assertFalse($first->equals(new LexicalSettings()));
        self::assertTrue($first->ansiQuotes);
        self::assertTrue($first->noBackslashEscapes);
        self::assertFalse($first->pipesAsConcat);
    }

    public function testUnknownLexicalFlagsAreConfigurationErrors(): void
    {
        $this->expectException(InvalidConstruction::class);
        new LexicalSettings('UNSPECIFIED_QUOTING');
    }
}

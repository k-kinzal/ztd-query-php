<?php

declare(strict_types=1);

namespace Tests\Unit\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\Mode;
use SqlSemantics\Platform\MySql\Mode as MySqlMode;

#[CoversClass(Mode::class)]
#[Small]
final class ModeTest extends TestCase
{
    public function testToStringAnswersTheCanonicalSpellingOfTheSettings(): void
    {
        $mode = MySqlMode::fromString('ANSI_QUOTES');

        self::assertSame('ANSI_QUOTES', $mode->toString());
    }
}

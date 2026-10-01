<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\SqlMode;
use SqlSemantics\Platform\MySql\Mode;

#[CoversClass(Mode::class)]
#[Small]
final class ModeTest extends TestCase
{
    public function testFromStringReadsTheSessionValue(): void
    {
        $mode = Mode::fromString('ANSI,NO_BACKSLASH_ESCAPES,STRICT_TRANS_TABLES');
        self::assertTrue($mode->sqlMode->ansiQuotes);
        self::assertTrue($mode->sqlMode->pipesAsConcat);
        self::assertTrue($mode->sqlMode->ignoreSpace);
        self::assertTrue($mode->sqlMode->noBackslashEscapes);
        self::assertFalse($mode->sqlMode->highNotPrecedence);
        self::assertSame('ANSI_QUOTES,PIPES_AS_CONCAT,NO_BACKSLASH_ESCAPES,IGNORE_SPACE', $mode->toString());
    }

    public function testToStringSpellsOnlyTheFlagsThatAreOn(): void
    {
        self::assertSame('', (new Mode())->toString());
        self::assertSame('HIGH_NOT_PRECEDENCE', (new Mode(new SqlMode(highNotPrecedence: true)))->toString());
    }
}

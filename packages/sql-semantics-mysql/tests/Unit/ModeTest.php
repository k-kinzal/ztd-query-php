<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Mode;

#[CoversClass(Mode::class)]
#[Medium]
final class ModeTest extends TestCase
{
    public function testFromStringReadsTheLexicalModesWhateverTheirCase(): void
    {
        $mode = Mode::fromString('STRICT_TRANS_TABLES,ansi_quotes,NO_BACKSLASH_ESCAPES,Pipes_As_Concat');

        self::assertTrue($mode->ansiQuotes);
        self::assertTrue($mode->pipesAsConcat);
        self::assertFalse($mode->highNotPrecedence);
        self::assertTrue($mode->noBackslashEscapes);
        self::assertFalse($mode->ignoreSpace);
    }

    public function testFromStringExpandsCombinationModes(): void
    {
        self::assertSame('ANSI_QUOTES,PIPES_AS_CONCAT,IGNORE_SPACE', Mode::fromString('ANSI')->toString());
        self::assertSame('', Mode::fromString('')->toString());
        self::assertSame('', Mode::fromString('STRICT_ALL_TABLES,REAL_AS_FLOAT')->toString());
    }

    public function testToStringSpellsTheRecordedModesInCanonicalOrder(): void
    {
        self::assertSame('ANSI_QUOTES,HIGH_NOT_PRECEDENCE,IGNORE_SPACE', (new Mode(true, false, true, false, true))->toString());
        self::assertSame('', (new Mode())->toString());
        self::assertSame('NO_BACKSLASH_ESCAPES', Mode::fromString('NO_BACKSLASH_ESCAPES')->toString());
    }

    public function testToStringFixesTheProfileLexicalSettings(): void
    {
        $semantics = new Semantics(Dialect::MySql, null, Mode::fromString('ANSI_QUOTES,NO_BACKSLASH_ESCAPES'));

        self::assertTrue($semantics->profile()->lexical->ansiQuotes);
        self::assertTrue($semantics->profile()->lexical->noBackslashEscapes);
        self::assertFalse($semantics->profile()->lexical->pipesAsConcat);
        self::assertSame('SELECT `order`, \'a\\nb\' FROM t', $semantics->analyze('SELECT "order", \'a\\nb\' FROM t')->toString());
    }
}

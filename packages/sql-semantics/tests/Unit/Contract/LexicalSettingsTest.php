<?php

declare(strict_types=1);

namespace Tests\Unit\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\LexicalSettings;

#[CoversClass(LexicalSettings::class)]
#[Small]
final class LexicalSettingsTest extends TestCase
{
    public function testEqualsIgnoresOrderAndRepetitionOfFlags(): void
    {
        $settings = new LexicalSettings('ANSI_QUOTES,PIPES_AS_CONCAT');
        $other = new LexicalSettings('PIPES_AS_CONCAT,ANSI_QUOTES,ANSI_QUOTES');

        self::assertTrue($settings->equals($other));
        self::assertTrue($settings->ansiQuotes);
        self::assertTrue($settings->pipesAsConcat);
        self::assertFalse($settings->highNotPrecedence);
        self::assertFalse($settings->noBackslashEscapes);
        self::assertFalse($settings->ignoreSpace);
    }

    public function testEqualsSeesEveryFlag(): void
    {
        $settings = new LexicalSettings('HIGH_NOT_PRECEDENCE,NO_BACKSLASH_ESCAPES,IGNORE_SPACE');

        self::assertFalse($settings->equals(new LexicalSettings('HIGH_NOT_PRECEDENCE,NO_BACKSLASH_ESCAPES')));
        self::assertTrue($settings->highNotPrecedence);
        self::assertTrue($settings->noBackslashEscapes);
        self::assertTrue($settings->ignoreSpace);
    }

    public function testEqualsHoldsForTwoEmptySettings(): void
    {
        self::assertTrue((new LexicalSettings())->equals(new LexicalSettings('')));
    }

    public function testEqualsIsNotReachedForAnUnknownFlag(): void
    {
        $this->expectExceptionMessage('The profile contains an unknown lexical setting.');

        new LexicalSettings('ANSI_QUOTES,STRICT_TRANS_TABLES');
    }
}

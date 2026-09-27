<?php

declare(strict_types=1);

namespace Tests\Unit\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\SqlMode;

#[CoversClass(SqlMode::class)]
#[Small]
final class SqlModeTest extends TestCase
{
    public function testDefault(): void
    {
        $mode = SqlMode::default();

        self::assertFalse($mode->ansiQuotes);
        self::assertFalse($mode->pipesAsConcat);
        self::assertFalse($mode->highNotPrecedence);
        self::assertFalse($mode->noBackslashEscapes);
        self::assertFalse($mode->ignoreSpace);
    }

    public function testDefaultDiffersFromAnExplicitMode(): void
    {
        self::assertTrue((new SqlMode(ansiQuotes: true, ignoreSpace: true))->ansiQuotes);
        self::assertTrue((new SqlMode(ansiQuotes: true, ignoreSpace: true))->ignoreSpace);
    }

    public function testFromStringReadsTheFlagsThatChangeTokenization(): void
    {
        $mode = SqlMode::fromString('ANSI_QUOTES,NO_BACKSLASH_ESCAPES,STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION');

        self::assertTrue($mode->ansiQuotes);
        self::assertTrue($mode->noBackslashEscapes);
        self::assertFalse($mode->pipesAsConcat);
        self::assertFalse($mode->highNotPrecedence);
        self::assertFalse($mode->ignoreSpace);
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['ANSI'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['DB2'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['MAXDB'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['MSSQL'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['ORACLE'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['POSTGRESQL'])]
    public function testFromStringExpandsAnsiLikeCombinationModes(string $combination): void
    {
        $mode = SqlMode::fromString($combination);

        self::assertTrue($mode->ansiQuotes);
        self::assertTrue($mode->pipesAsConcat);
        self::assertTrue($mode->ignoreSpace);
        self::assertFalse($mode->highNotPrecedence);
        self::assertFalse($mode->noBackslashEscapes);
    }

    public function testFromStringExpandsTheOldClientCombinationModes(): void
    {
        self::assertTrue(SqlMode::fromString('MYSQL323')->highNotPrecedence);
        self::assertTrue(SqlMode::fromString('MYSQL40')->highNotPrecedence);
        self::assertFalse(SqlMode::fromString('MYSQL40')->ansiQuotes);
    }

    public function testFromStringIgnoresCaseSpacingAndAnEmptyValue(): void
    {
        $mode = SqlMode::fromString(' pipes_as_concat , High_Not_Precedence ');

        self::assertTrue($mode->pipesAsConcat);
        self::assertTrue($mode->highNotPrecedence);
        self::assertFalse($mode->ansiQuotes);
        self::assertEquals(SqlMode::default(), SqlMode::fromString(''));
        self::assertEquals(SqlMode::default(), SqlMode::fromString('TRADITIONAL'));
    }
}

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
}

<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Ast;

use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\Bison\Ast\BisonTokenInfo;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(BisonTokenInfo::class)]
final class BisonTokenInfoTest extends TestCase
{
    public function testName(): void
    {
        $info = new BisonTokenInfo('SELECT', null, null);

        self::assertSame('SELECT', $info->name);
    }

    public function testNumber(): void
    {
        $info = new BisonTokenInfo('TOKEN', 42, null);

        self::assertSame(42, $info->number);
    }

    public function testNumberNull(): void
    {
        $info = new BisonTokenInfo('TOKEN', null, null);

        self::assertNull($info->number);
    }

    public function testAlias(): void
    {
        $info = new BisonTokenInfo('TOKEN', null, '"alias"');

        self::assertSame('"alias"', $info->alias);
    }

    public function testAliasNull(): void
    {
        $info = new BisonTokenInfo('TOKEN', null, null);

        self::assertNull($info->alias);
    }
}

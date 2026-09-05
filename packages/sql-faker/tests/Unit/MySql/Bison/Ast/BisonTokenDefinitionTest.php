<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\Bison\Ast\BisonTokenDefinition;

#[CoversClass(BisonTokenDefinition::class)]
final class BisonTokenDefinitionTest extends TestCase
{
    public function testName(): void
    {
        $info = new BisonTokenDefinition('SELECT', null, null);

        self::assertSame('SELECT', $info->name);
    }

    public function testNumber(): void
    {
        $info = new BisonTokenDefinition('TOKEN', 42, null);

        self::assertSame(42, $info->number);
    }

    public function testNumberNull(): void
    {
        $info = new BisonTokenDefinition('TOKEN', null, null);

        self::assertNull($info->number);
    }

    public function testAlias(): void
    {
        $info = new BisonTokenDefinition('TOKEN', null, '"alias"');

        self::assertSame('"alias"', $info->alias);
    }

    public function testAliasNull(): void
    {
        $info = new BisonTokenDefinition('TOKEN', null, null);

        self::assertNull($info->alias);
    }
}

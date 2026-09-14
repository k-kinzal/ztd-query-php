<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite\LoadData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Rewrite\LoadData\RowProjector;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Value\MySqlCastRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\MySqlLexerProfile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Value\MySqlValueRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Value\CastTypeResolver::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Value\ScalarExpression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Value\StringCoercion::class)]
#[CoversClass(RowProjector::class)]
final class RowProjectorTest extends TestCase
{
    public function testProjectRow(): void
    {
        $projector = new RowProjector(new \ZtdQuery\Platform\MySql\Sql\Value\MySqlValueRenderer());
        self::assertSame(['id' => "CAST('7' AS CHAR)", 'name' => "UPPER(CAST('hello' AS CHAR))", 'missing' => 'DEFAULT'], $projector->projectRow(['id', '@raw', 'name', 'missing'], ['name' => 'UPPER(@raw)'], ['7', 'hello', null]));
    }

    public function testSubstituteVariables(): void
    {
        $projector = new RowProjector(new \ZtdQuery\Platform\MySql\Sql\Value\MySqlValueRenderer());
        self::assertSame("CONCAT(CAST('hello' AS CHAR), NULL, @missing, @@sql_mode, '@raw')", $projector->substituteVariables("CONCAT(@RAW, @empty, @missing, @@sql_mode, '@raw')", ['raw' => 'hello', 'empty' => null]));
    }

}

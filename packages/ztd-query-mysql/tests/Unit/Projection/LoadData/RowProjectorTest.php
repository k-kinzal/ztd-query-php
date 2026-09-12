<?php

declare(strict_types=1);

namespace Tests\Unit\Projection\LoadData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Projection\LoadData\RowProjector;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlCastRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlValueRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Type\CastTypeResolver::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Type\Value\ScalarExpression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Type\Value\StringCoercion::class)]
#[CoversClass(RowProjector::class)]
final class RowProjectorTest extends TestCase
{
    public function testProjectRow(): void
    {
        $projector = new RowProjector(new \ZtdQuery\Platform\MySql\MySqlValueRenderer());
        self::assertSame(['id' => "CAST('7' AS CHAR)", 'name' => "UPPER(CAST('hello' AS CHAR))", 'missing' => 'DEFAULT'], $projector->projectRow(['id', '@raw', 'name', 'missing'], ['name' => 'UPPER(@raw)'], ['7', 'hello', null]));
    }

    public function testSubstituteVariables(): void
    {
        $projector = new RowProjector(new \ZtdQuery\Platform\MySql\MySqlValueRenderer());
        self::assertSame("CONCAT(CAST('hello' AS CHAR), NULL, @missing, @@sql_mode, '@raw')", $projector->substituteVariables("CONCAT(@RAW, @empty, @missing, @@sql_mode, '@raw')", ['raw' => 'hello', 'empty' => null]));
    }

}

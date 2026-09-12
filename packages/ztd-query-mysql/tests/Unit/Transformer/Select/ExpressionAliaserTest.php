<?php

declare(strict_types=1);

namespace Tests\Unit\Transformer\Select;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Transformer\Select\ExpressionAliaser;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
#[CoversClass(ExpressionAliaser::class)]
final class ExpressionAliaserTest extends TestCase
{
    public function testEndsSelectList(): void
    {
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize('FROM id', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        self::assertTrue((new ExpressionAliaser())->endsSelectList($tokens[0]));
        self::assertFalse((new ExpressionAliaser())->endsSelectList($tokens[1]));
    }

    public function testContainsWildcard(): void
    {
        $aliaser = new ExpressionAliaser();
        self::assertTrue($aliaser->containsWildcard(['id', 't.*']));
        self::assertTrue($aliaser->containsWildcard(['']));
        self::assertFalse($aliaser->containsWildcard(['COUNT(*)', 'id * 2']));
    }

    public function testRemoveModifiers(): void
    {
        $aliaser = new ExpressionAliaser();
        self::assertSame(['modifiers' => 'DISTINCT SQL_NO_CACHE ', 'expression' => 'id'], $aliaser->removeModifiers('DISTINCT SQL_NO_CACHE id'));
        self::assertSame(['modifiers' => '', 'expression' => 'id'], $aliaser->removeModifiers('id'));
    }

    public function testIsModifier(): void
    {
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize('DISTINCT id', \ZtdQuery\Platform\MySql\MySqlLexerProfile::create())->significantTokens();
        self::assertTrue((new ExpressionAliaser())->isModifier($tokens[0]));
        self::assertFalse((new ExpressionAliaser())->isModifier($tokens[1]));
    }

    public function testWithoutExplicitAlias(): void
    {
        $aliaser = new ExpressionAliaser();
        self::assertSame('CAST(id AS SIGNED)', $aliaser->withoutExplicitAlias('CAST(id AS SIGNED) AS x'));
        self::assertSame('id implicit', $aliaser->withoutExplicitAlias('id implicit'));
    }

}

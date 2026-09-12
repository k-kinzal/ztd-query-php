<?php

declare(strict_types=1);

namespace Tests\Unit\Transformer\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Transformer\Insert\ResultProjection;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlCastRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlIdentifierQuoter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\InsertRowRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\InsertSelectRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\Insert\InsertTarget::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\MySqlSelectListAliaser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\Select\ExpressionAliaser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Type\CastTypeResolver::class)]
#[CoversClass(ResultProjection::class)]
final class ResultProjectionTest extends TestCase
{
    public function testBuildInsertSelect(): void
    {
        $projection = new ResultProjection(new \ZtdQuery\Platform\MySql\MySqlCastRenderer(), new \ZtdQuery\Rewrite\ShadowIdentityAllocator(), new \ZtdQuery\Platform\MySql\Transformer\InsertSelectRenderer(), new \ZtdQuery\Platform\MySql\Transformer\InsertRowRenderer());
        $target = new \ZtdQuery\Platform\MySql\Transformer\Insert\InsertTarget('t', ['id', 'name'], ['name'], [], ['name' => "'default'"], ['id' => \ZtdQuery\Schema\IdentityGenerationStrategy::MaxValue], [['id' => 7]], []);
        $statement = (new \PhpMyAdmin\SqlParser\Parser("INSERT INTO t (name) VALUES ('a'), ('b')"))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\InsertStatement::class, $statement);
        self::assertSame("SELECT 8 AS `id`, 'a' AS `name` UNION ALL SELECT 9 AS `id`, 'b' AS `name`", $projection->buildInsertSelect($statement, $target, null));
    }

    public function testBuildInsertRowSelect(): void
    {
        $projection = new ResultProjection(new \ZtdQuery\Platform\MySql\MySqlCastRenderer(), new \ZtdQuery\Rewrite\ShadowIdentityAllocator(), new \ZtdQuery\Platform\MySql\Transformer\InsertSelectRenderer(), new \ZtdQuery\Platform\MySql\Transformer\InsertRowRenderer());
        $target = new \ZtdQuery\Platform\MySql\Transformer\Insert\InsertTarget('t', ['id', 'name'], ['name'], [], ['name' => "'default'"], ['id' => \ZtdQuery\Schema\IdentityGenerationStrategy::MaxValue], [['id' => 7]], []);
        $statement = (new \PhpMyAdmin\SqlParser\Parser('INSERT INTO t (name) VALUES (DEFAULT)'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\InsertStatement::class, $statement);
        self::assertNotNull($statement->values);
        self::assertSame("SELECT 8 AS `id`, 'default' AS `name`", $projection->buildInsertRowSelect($statement->values[0], $target));
    }

    public function testBuildInsertSetSelect(): void
    {
        $projection = new ResultProjection(new \ZtdQuery\Platform\MySql\MySqlCastRenderer(), new \ZtdQuery\Rewrite\ShadowIdentityAllocator(), new \ZtdQuery\Platform\MySql\Transformer\InsertSelectRenderer(), new \ZtdQuery\Platform\MySql\Transformer\InsertRowRenderer());
        $target = new \ZtdQuery\Platform\MySql\Transformer\Insert\InsertTarget('t', ['id', 'name'], ['name'], [], ['name' => "'default'"], ['id' => \ZtdQuery\Schema\IdentityGenerationStrategy::MaxValue], [['id' => 7]], []);
        $statement = (new \PhpMyAdmin\SqlParser\Parser("INSERT INTO t SET name = 'a'"))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\InsertStatement::class, $statement);
        self::assertNotNull($statement->set);
        self::assertSame("SELECT 8 AS `id`, 'a' AS `name`", $projection->buildInsertSetSelect(array_values($statement->set), $target));
    }

    public function testOrderedValues(): void
    {
        self::assertSame(['first', 'second'], ResultProjection::orderedValues([8 => 'first', 2 => 'second']));
        self::assertSame([], ResultProjection::orderedValues([]));
    }

}

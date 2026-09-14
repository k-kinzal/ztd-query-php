<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite\Transformer\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\Insert\ResultProjection;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Value\MySqlCastRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\MySqlIdentifierQuoter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\MySqlLexerProfile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\InsertRowRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\InsertSelectRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Insert\InsertTarget::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\MySqlSelectListAliaser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Select\ExpressionAliaser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Value\CastTypeResolver::class)]
#[CoversClass(ResultProjection::class)]
final class ResultProjectionTest extends TestCase
{
    public function testBuildInsertSelect(): void
    {
        $projection = new ResultProjection(new \ZtdQuery\Platform\MySql\Sql\Value\MySqlCastRenderer(), new \ZtdQuery\Rewrite\ShadowIdentityAllocator(), new \ZtdQuery\Platform\MySql\Rewrite\Transformer\InsertSelectRenderer(), new \ZtdQuery\Platform\MySql\Rewrite\Transformer\InsertRowRenderer());
        $target = new \ZtdQuery\Platform\MySql\Rewrite\Transformer\Insert\InsertTarget('t', ['id', 'name'], ['name'], [], ['name' => "'default'"], ['id' => \ZtdQuery\Schema\Key\IdentityGenerationStrategy::MaxValue], [['id' => 7]], []);
        $statement = (new \PhpMyAdmin\SqlParser\Parser("INSERT INTO t (name) VALUES ('a'), ('b')"))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\InsertStatement::class, $statement);
        self::assertSame("SELECT 8 AS `id`, 'a' AS `name` UNION ALL SELECT 9 AS `id`, 'b' AS `name`", $projection->buildInsertSelect($statement, $target, null));
    }

    public function testBuildInsertRowSelect(): void
    {
        $projection = new ResultProjection(new \ZtdQuery\Platform\MySql\Sql\Value\MySqlCastRenderer(), new \ZtdQuery\Rewrite\ShadowIdentityAllocator(), new \ZtdQuery\Platform\MySql\Rewrite\Transformer\InsertSelectRenderer(), new \ZtdQuery\Platform\MySql\Rewrite\Transformer\InsertRowRenderer());
        $target = new \ZtdQuery\Platform\MySql\Rewrite\Transformer\Insert\InsertTarget('t', ['id', 'name'], ['name'], [], ['name' => "'default'"], ['id' => \ZtdQuery\Schema\Key\IdentityGenerationStrategy::MaxValue], [['id' => 7]], []);
        $statement = (new \PhpMyAdmin\SqlParser\Parser('INSERT INTO t (name) VALUES (DEFAULT)'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\InsertStatement::class, $statement);
        self::assertNotNull($statement->values);
        self::assertSame("SELECT 8 AS `id`, 'default' AS `name`", $projection->buildInsertRowSelect($statement->values[0], $target));
    }

    public function testBuildInsertSetSelect(): void
    {
        $projection = new ResultProjection(new \ZtdQuery\Platform\MySql\Sql\Value\MySqlCastRenderer(), new \ZtdQuery\Rewrite\ShadowIdentityAllocator(), new \ZtdQuery\Platform\MySql\Rewrite\Transformer\InsertSelectRenderer(), new \ZtdQuery\Platform\MySql\Rewrite\Transformer\InsertRowRenderer());
        $target = new \ZtdQuery\Platform\MySql\Rewrite\Transformer\Insert\InsertTarget('t', ['id', 'name'], ['name'], [], ['name' => "'default'"], ['id' => \ZtdQuery\Schema\Key\IdentityGenerationStrategy::MaxValue], [['id' => 7]], []);
        $statement = (new \PhpMyAdmin\SqlParser\Parser("INSERT INTO t SET name = 'a'"))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\InsertStatement::class, $statement);
        self::assertNotNull($statement->set);
        self::assertSame("SELECT 8 AS `id`, 'a' AS `name`", $projection->buildInsertSetSelect(array_values($statement->set), $target));
    }

    public function testBuildInsertSourceSelectCastsDestinationColumns(): void
    {
        $projection = new ResultProjection(new \ZtdQuery\Platform\MySql\Sql\Value\MySqlCastRenderer(), new \ZtdQuery\Rewrite\ShadowIdentityAllocator(), new \ZtdQuery\Platform\MySql\Rewrite\Transformer\InsertSelectRenderer(), new \ZtdQuery\Platform\MySql\Rewrite\Transformer\InsertRowRenderer());
        $target = new \ZtdQuery\Platform\MySql\Rewrite\Transformer\Insert\InsertTarget('years', ['year', 'note`tag'], [], ['year' => new \ZtdQuery\Schema\ColumnDeclaration(\ZtdQuery\Schema\ColumnTypeFamily::INTEGER, 'YEAR')], [], [], [], []);
        $statement = (new \PhpMyAdmin\SqlParser\Parser("SELECT 93, 'ok'"))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\SelectStatement::class, $statement);
        self::assertStringStartsWith(
            'SELECT CAST(CAST(_ztd_insert_cast.`year` AS YEAR) AS SIGNED) AS `year`, _ztd_insert_cast.`note``tag` AS `note``tag` FROM (',
            $projection->buildInsertSourceSelect($statement, $target, null),
        );
    }

    public function testOrderedValues(): void
    {
        self::assertSame(['first', 'second'], ResultProjection::orderedValues([8 => 'first', 2 => 'second']));
        self::assertSame([], ResultProjection::orderedValues([]));
    }

}

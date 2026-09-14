<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite\Transformer\Shadow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\Shadow\CteRows;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Value\MySqlCastRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\GeneratedColumn\MySqlGeneratedColumnProjector::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\MySqlIdentifierQuoter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Value\MySqlValueRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Set\ValueNormalizer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Value\CastTypeResolver::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Value\ScalarExpression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Value\StringCoercion::class)]
#[CoversClass(CteRows::class)]
final class CteRowsTest extends TestCase
{
    public function testGenerateCte(): void
    {
        $rows = new CteRows(new \ZtdQuery\Platform\MySql\Sql\Value\MySqlCastRenderer(), new \ZtdQuery\Platform\MySql\Rewrite\GeneratedColumn\MySqlGeneratedColumnProjector(), new \ZtdQuery\Platform\MySql\Sql\MySqlIdentifierQuoter(), new \ZtdQuery\Platform\MySql\Sql\Value\MySqlValueRenderer());
        self::assertSame('`t` AS (SELECT CAST(1 AS SIGNED) AS `id` UNION ALL SELECT NULL AS `id`)', $rows->generateCte('t', [['id' => 1], ['id' => null]], ['id'], [], []));
        self::assertSame('`t` AS (SELECT CAST(1 AS SIGNED) AS `id`)', $rows->generateCte('t', [['id' => 1]], [], [], []));
    }

    public function testGenerateCteRejectsUnknownEmptyColumns(): void
    {
        $rows = new CteRows(new \ZtdQuery\Platform\MySql\Sql\Value\MySqlCastRenderer(), new \ZtdQuery\Platform\MySql\Rewrite\GeneratedColumn\MySqlGeneratedColumnProjector(), new \ZtdQuery\Platform\MySql\Sql\MySqlIdentifierQuoter(), new \ZtdQuery\Platform\MySql\Sql\Value\MySqlValueRenderer());
        $this->expectException(RuntimeException::class);
        $rows->generateCte('t', [], [], [], []);
    }

    public function testWrapCte(): void
    {
        $rows = new CteRows(new \ZtdQuery\Platform\MySql\Sql\Value\MySqlCastRenderer(), new \ZtdQuery\Platform\MySql\Rewrite\GeneratedColumn\MySqlGeneratedColumnProjector(), new \ZtdQuery\Platform\MySql\Sql\MySqlIdentifierQuoter(), new \ZtdQuery\Platform\MySql\Sql\Value\MySqlValueRenderer());
        self::assertSame('`t` AS (SELECT 1 AS id)', $rows->wrapCte('`t`', 'SELECT 1 AS id', ['id'], []));
    }

    public function testFormatValue(): void
    {
        $rows = new CteRows(new \ZtdQuery\Platform\MySql\Sql\Value\MySqlCastRenderer(), new \ZtdQuery\Platform\MySql\Rewrite\GeneratedColumn\MySqlGeneratedColumnProjector(), new \ZtdQuery\Platform\MySql\Sql\MySqlIdentifierQuoter(), new \ZtdQuery\Platform\MySql\Sql\Value\MySqlValueRenderer());
        $type = new \ZtdQuery\Schema\ColumnDeclaration(\ZtdQuery\Schema\ColumnTypeFamily::STRING, "SET('red','blue')");
        self::assertSame("CAST('red,blue' AS CHAR)", $rows->formatValue('blue,red,blue', $type));
        self::assertSame('NULL', $rows->formatValue(null));
    }

    public function testEmptySelect(): void
    {
        $rows = new CteRows(new \ZtdQuery\Platform\MySql\Sql\Value\MySqlCastRenderer(), new \ZtdQuery\Platform\MySql\Rewrite\GeneratedColumn\MySqlGeneratedColumnProjector(), new \ZtdQuery\Platform\MySql\Sql\MySqlIdentifierQuoter(), new \ZtdQuery\Platform\MySql\Sql\Value\MySqlValueRenderer());
        self::assertSame('SELECT CAST(NULL AS CHAR) AS `name` FROM DUAL WHERE 0', $rows->emptySelect(['name'], []));
    }

}

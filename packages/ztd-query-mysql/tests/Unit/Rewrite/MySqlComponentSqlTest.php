<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use PhpMyAdmin\SqlParser\Components\Expression;
use PhpMyAdmin\SqlParser\Components\Limit;
use PhpMyAdmin\SqlParser\Components\OrderKeyword;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\DeleteStatement;
use PhpMyAdmin\SqlParser\Statements\SelectStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixture\MySqlAlterStatements;
use ZtdQuery\Platform\MySql\Rewrite\MySqlComponentSql;

#[CoversClass(MySqlComponentSql::class)]
final class MySqlComponentSqlTest extends TestCase
{
    public function testExpressionWritesTheExpressionBackOut(): void
    {
        $expression = new Expression();
        $expression->expr = 'users';

        self::assertSame('users', (new MySqlComponentSql())->expression($expression, 'SELECT 1'));
    }

    public function testJoinsWritesTheJoinsBackOut(): void
    {
        $statement = (new Parser('SELECT * FROM a JOIN b ON a.id = b.id'))->statements[0];
        self::assertInstanceOf(SelectStatement::class, $statement);

        self::assertStringContainsString(
            'JOIN b',
            (new MySqlComponentSql())->joins($statement->join ?? [], 'SELECT 1'),
        );
    }

    public function testOrderWritesTheOrderingTermBackOut(): void
    {
        $statement = (new Parser('SELECT * FROM t ORDER BY id DESC'))->statements[0];
        self::assertInstanceOf(SelectStatement::class, $statement);
        $order = $statement->order[0] ?? null;
        self::assertInstanceOf(OrderKeyword::class, $order);

        self::assertStringContainsString('id', (new MySqlComponentSql())->order($order, 'SELECT 1'));
    }

    public function testLimitWritesTheLimitBackOut(): void
    {
        $statement = (new Parser('SELECT * FROM t LIMIT 5'))->statements[0];
        self::assertInstanceOf(SelectStatement::class, $statement);
        $limit = $statement->limit;
        self::assertInstanceOf(Limit::class, $limit);

        self::assertSame('0, 5', (new MySqlComponentSql())->limit($limit, 'SELECT 1'));
    }

    public function testConditionWritesTheConditionsBackOut(): void
    {
        $statement = (new Parser('DELETE FROM t WHERE id = 1'))->statements[0];
        self::assertInstanceOf(DeleteStatement::class, $statement);
        self::assertSame(
            'id = 1',
            (new MySqlComponentSql())->condition($statement->where ?? [], 'DELETE FROM t'),
        );
    }

    public function testAlterOperationWritesTheOperationBackOut(): void
    {
        $operation = MySqlAlterStatements::operationOn('DROP COLUMN name');

        self::assertStringContainsString(
            'name',
            (new MySqlComponentSql())->alterOperation($operation, 'ALTER TABLE t DROP COLUMN name'),
        );
    }
}

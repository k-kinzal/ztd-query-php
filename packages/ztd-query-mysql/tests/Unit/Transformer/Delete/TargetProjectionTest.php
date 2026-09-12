<?php

declare(strict_types=1);

namespace Tests\Unit\Transformer\Delete;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Transformer\Delete\TargetProjection;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlIdentifierQuoter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\Relation\ExpressionNames::class)]
#[CoversClass(TargetProjection::class)]
final class TargetProjectionTest extends TestCase
{
    public function testTargetsFromContexts(): void
    {
        $targets = (new TargetProjection())->targetsFromContexts(['users' => ['alias' => 'u'], 'missing' => ['alias' => 'm']], ['users' => ['columns' => ['id', 'name'], 'primaryKeys' => ['id']]]);
        self::assertCount(1, $targets);
        self::assertSame('users', $targets[0]->tableName());
        self::assertSame(['id', 'name'], $targets[0]->columns());
        self::assertSame(['id'], $targets[0]->primaryKeys());
    }

    public function testMultiTableSelectList(): void
    {
        $target = new \ZtdQuery\Shadow\Mutation\MultiTableMutationTarget('users', ['id', 'name'], ['id']);
        $codec = new \ZtdQuery\Shadow\Mutation\MultiTableMutationRow();
        self::assertSame('`u`.`id` AS `' . $codec->valueColumn(0, 0) . '`', (new TargetProjection())->multiTableSelectList(['users' => ['alias' => 'u']], [$target]));
    }

    public function testResolveAliasToTable(): void
    {
        $statement = (new \PhpMyAdmin\SqlParser\Parser('DELETE u, p FROM users u JOIN posts p ON p.uid = u.id'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);
        $projection = new TargetProjection();
        self::assertSame('users', $projection->resolveAliasToTable('u', $statement));
        self::assertSame('posts', $projection->resolveAliasToTable('p', $statement));
        self::assertSame('unknown', $projection->resolveAliasToTable('unknown', $statement));
    }

}

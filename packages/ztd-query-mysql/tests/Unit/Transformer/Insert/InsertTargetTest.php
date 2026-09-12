<?php

declare(strict_types=1);

namespace Tests\Unit\Transformer\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Transformer\Insert\InsertTarget;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlIdentifierQuoter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\InsertRowRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\InsertSelectRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\Insert\ResultProjection::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\MySqlSelectListAliaser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\Select\ExpressionAliaser::class)]
#[CoversClass(InsertTarget::class)]
final class InsertTargetTest extends TestCase
{
    public function testFromStatement(): void
    {
        $statement = (new \PhpMyAdmin\SqlParser\Parser("INSERT INTO t (name) VALUES ('new')"))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\InsertStatement::class, $statement);
        $target = InsertTarget::fromStatement($statement, ['t' => [
            'rows' => [['id' => 7, 'name' => 'old', 'ignored' => null, 'float' => 1.5]],
            'columns' => ['id', 'name'], 'columnTypes' => [], 'columnDefaults' => ['name' => "'default'"],
            'identityStrategies' => ['id' => \ZtdQuery\Schema\IdentityGenerationStrategy::MaxValue],
            'candidateKeys' => ['PRIMARY' => ['id']],
        ]], $statement->build());
        self::assertSame('t', $target->tableName);
        self::assertSame(['id', 'name'], $target->tableColumns);
        self::assertSame(['name'], $target->insertColumns);
        self::assertSame([['id' => 7, 'name' => 'old']], $target->existingRows);
        self::assertSame(['name' => "'default'"], $target->columnDefaults);
        self::assertSame(['id' => \ZtdQuery\Schema\IdentityGenerationStrategy::MaxValue], $target->identityStrategies);
        self::assertSame(['PRIMARY' => ['id']], $target->candidateKeys);
    }

    public function testFromStatementRejectsUnknownColumns(): void
    {
        $statement = (new \PhpMyAdmin\SqlParser\Parser('INSERT INTO t VALUES (1)'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\InsertStatement::class, $statement);
        $this->expectException(\ZtdQuery\Exception\UnsupportedSqlException::class);
        InsertTarget::fromStatement($statement, [], $statement->build());
    }

}

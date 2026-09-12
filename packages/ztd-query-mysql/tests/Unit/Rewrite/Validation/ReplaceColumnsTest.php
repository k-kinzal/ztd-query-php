<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Rewrite\Validation\ReplaceColumns;

#[CoversClass(ReplaceColumns::class)]
final class ReplaceColumnsTest extends TestCase
{
    public function testEnsureReplaceColumns(): void
    {
        $statement = (new \PhpMyAdmin\SqlParser\Parser('REPLACE INTO users VALUES (1)'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\ReplaceStatement::class, $statement);
        $columns = new ReplaceColumns(new \ZtdQuery\Schema\TableDefinitionRegistry(), new \ZtdQuery\Shadow\ShadowStore());
        $this->expectException(\ZtdQuery\Exception\UnsupportedSqlException::class);
        $this->expectExceptionMessage('Cannot determine columns');
        $columns->ensureReplaceColumns($statement, $statement->build());
    }

    public function testResolveIntoTableName(): void
    {
        $statement = (new \PhpMyAdmin\SqlParser\Parser('REPLACE INTO users (id) VALUES (1)'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\ReplaceStatement::class, $statement);
        self::assertSame('users', ReplaceColumns::resolveIntoTableName($statement->into));
        self::assertNull(ReplaceColumns::resolveIntoTableName(null));
    }

}

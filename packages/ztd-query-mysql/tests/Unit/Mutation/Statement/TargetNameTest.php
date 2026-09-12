<?php

declare(strict_types=1);

namespace Tests\Unit\Mutation\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Mutation\Statement\TargetName;

#[CoversClass(TargetName::class)]
final class TargetNameTest extends TestCase
{
    public function testResolveIntoTableName(): void
    {
        $statement = (new \PhpMyAdmin\SqlParser\Parser('INSERT INTO db.t (id) VALUES (1)'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\InsertStatement::class, $statement);
        self::assertSame('t', TargetName::resolveIntoTableName($statement->into));
        self::assertNull(TargetName::resolveIntoTableName(null));
    }

}

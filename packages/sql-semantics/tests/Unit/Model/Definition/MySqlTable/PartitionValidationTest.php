<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\PartitionValidation;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PartitionValidation::class)]
#[Medium]
final class PartitionValidationTest extends TestCase
{
    public function testIsReadFromTheStatement(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t WITH VALIDATION, FORCE');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertSame(PartitionValidation::With, $statement->validation);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TableAlteration::class)]
#[Medium]
final class TableAlterationTest extends TestCase
{
    public function testClassifiesEveryAlterationForm(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ADD c INT, FORCE');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertContainsOnlyInstancesOf(TableAlteration::class, $statement->alterations);
    }
}

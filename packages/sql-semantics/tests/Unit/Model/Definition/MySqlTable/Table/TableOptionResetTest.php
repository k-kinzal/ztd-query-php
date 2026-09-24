<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Table\ChangeTableOptions;
use SqlSemantics\Model\Definition\MySqlTable\Table\TableOptionReset;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TableOptionReset::class)]
#[Medium]
final class TableOptionResetTest extends TestCase
{
    public function testIsReadFromDefaultAndNull(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t STATS_PERSISTENT = DEFAULT SECONDARY_ENGINE = NULL');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(ChangeTableOptions::class, $alteration);
        self::assertSame([TableOptionReset::StatsPersistent, TableOptionReset::SecondaryEngine], $alteration->resets);
    }
}

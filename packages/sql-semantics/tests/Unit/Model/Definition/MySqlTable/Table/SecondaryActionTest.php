<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\PartitionChange\SecondaryLoad;
use SqlSemantics\Model\Definition\MySqlTable\Table\SecondaryAction;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SecondaryAction::class)]
#[Medium]
final class SecondaryActionTest extends TestCase
{
    public function testIsReadFromSecondaryEngineCommands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t SECONDARY_LOAD');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(SecondaryLoad::class, $alteration);
        self::assertSame(SecondaryAction::Load, $alteration->action);
        self::assertSame([], $alteration->partitions);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Column\FirstColumn;
use SqlSemantics\Model\Definition\MySqlTable\Column\ModifyColumn;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(FirstColumn::class)]
#[Medium]
final class FirstColumnTest extends TestCase
{
    public function testSpellsTheKeyword(): void
    {
        self::assertSame(FirstColumn::First, FirstColumn::from('FIRST'));
    }

    public function testIsReadFromAModification(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t MODIFY n BIGINT FIRST');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(ModifyColumn::class, $alteration);
        self::assertSame(FirstColumn::First, $alteration->position);
    }
}

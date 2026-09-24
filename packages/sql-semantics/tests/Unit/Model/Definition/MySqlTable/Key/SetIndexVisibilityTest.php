<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Key\SetIndexVisibility;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SetIndexVisibility::class)]
#[Medium]
final class SetIndexVisibilityTest extends TestCase
{
    public function testReadsTheVisibility(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ALTER INDEX ix VISIBLE');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(SetIndexVisibility::class, $alteration);
        self::assertSame(['ix', true], [$alteration->index, $alteration->visible]);
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidStructure::class);
        new SetIndexVisibility('', true);
    }
}

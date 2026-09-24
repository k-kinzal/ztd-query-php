<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Table\ConvertCharacterSet;
use SqlSemantics\Model\Definition\MySqlTable\Table\InheritedCharacterSet;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ConvertCharacterSet::class)]
#[Medium]
final class ConvertCharacterSetTest extends TestCase
{
    public function testReadsTheCharacterSetAndCollation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t CONVERT TO CHARACTER SET latin1 COLLATE latin1_bin');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(ConvertCharacterSet::class, $alteration);
        self::assertSame(['latin1', 'latin1_bin'], [$alteration->characterSet, $alteration->collation]);
    }

    public function testRejectsAnEmptyCollation(): void
    {
        $this->expectException(InvalidStructure::class);
        new ConvertCharacterSet(InheritedCharacterSet::Database, '');
    }
}

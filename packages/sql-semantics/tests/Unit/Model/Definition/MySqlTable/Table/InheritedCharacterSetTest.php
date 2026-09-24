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
use SqlSemantics\SchemaBuilder;

#[CoversClass(InheritedCharacterSet::class)]
#[Medium]
final class InheritedCharacterSetTest extends TestCase
{
    public function testIsReadFromDefault(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t CONVERT TO CHARACTER SET DEFAULT');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(ConvertCharacterSet::class, $alteration);
        self::assertSame(InheritedCharacterSet::Database, $alteration->characterSet);
    }
}

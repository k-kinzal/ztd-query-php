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
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Table\MySqlProperties;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ChangeTableOptions::class)]
#[Medium]
final class ChangeTableOptionsTest extends TestCase
{
    public function testReadsOptionsAndResets(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ENGINE = InnoDB PACK_KEYS = DEFAULT');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(ChangeTableOptions::class, $alteration);
        self::assertSame('InnoDB', $alteration->options->engine);
        self::assertSame([TableOptionReset::PackKeys], $alteration->resets);
    }

    public function testRejectsAnOptionSetAndReset(): void
    {
        $this->expectException(InvalidStructure::class);
        new ChangeTableOptions(new MySqlProperties(packKeys: true), [TableOptionReset::PackKeys]);
    }

    public function testRejectsATemporaryTable(): void
    {
        $this->expectException(InvalidStructure::class);
        new ChangeTableOptions(new MySqlProperties(temporary: true));
    }
}

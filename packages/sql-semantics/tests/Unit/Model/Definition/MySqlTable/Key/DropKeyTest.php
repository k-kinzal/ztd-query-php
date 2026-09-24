<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Key\DropKey;
use SqlSemantics\Model\Definition\MySqlTable\Key\KeyKind;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DropKey::class)]
#[Medium]
final class DropKeyTest extends TestCase
{
    #[TestWith(['ALTER TABLE t DROP INDEX k', 'INDEX'])]
    #[TestWith(['ALTER TABLE t DROP KEY k', 'INDEX'])]
    #[TestWith(['ALTER TABLE t DROP FOREIGN KEY k', 'FOREIGN KEY'])]
    #[TestWith(['ALTER TABLE t DROP CHECK k', 'CHECK'])]
    #[TestWith(['ALTER TABLE t DROP CONSTRAINT k', 'CONSTRAINT'])]
    public function testReadsTheNameAndKind(string $sql, string $kind): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind($sql);
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(DropKey::class, $alteration);
        self::assertSame(['k', $kind], [$alteration->name, $alteration->kind->value]);
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidStructure::class);
        new DropKey('', KeyKind::Index);
    }
}

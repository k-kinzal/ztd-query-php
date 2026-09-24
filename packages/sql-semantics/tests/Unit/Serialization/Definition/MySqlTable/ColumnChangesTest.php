<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\MySqlTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Definition\MySqlTable\Column\AfterColumn;
use SqlSemantics\Model\Definition\MySqlTable\Column\ColumnDefaultAssignment;
use SqlSemantics\Model\Definition\MySqlTable\Column\ModifyColumn;
use SqlSemantics\Model\Definition\MySqlTable\Key\AddIndex;
use SqlSemantics\Model\Definition\MySqlTable\Table\TableCommand;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\MySqlTable\ColumnChanges;

#[CoversClass(ColumnChanges::class)]
#[Medium]
final class ColumnChangesTest extends TestCase
{
    public function testWriteWritesColumnAndKeyAlterations(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ADD COLUMN c INT UNIQUE FIRST, ALTER INDEX ix VISIBLE, DROP CONSTRAINT k');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertSame(['ADD COLUMN `c` integer UNIQUE FIRST', 'ALTER INDEX `ix` VISIBLE', 'DROP CONSTRAINT `k`'], array_map(static fn ($alteration): string => ColumnChanges::write($alteration)?->toString() ?? '', $statement->alterations));
    }

    public function testWriteReturnsNullForTableAlterations(): void
    {
        self::assertNull(ColumnChanges::write(TableCommand::Force));
    }

    public function testDeclarationWritesLocalConstraints(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t MODIFY n INT PRIMARY KEY');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(ModifyColumn::class, $alteration);
        self::assertSame('`n` integer PRIMARY KEY', ColumnChanges::declaration($alteration->definition, $alteration->constraints)->toString());
    }

    public function testPositionWritesAfter(): void
    {
        self::assertSame('AFTER `id`', ColumnChanges::position(new AfterColumn('id'))->toString());
        self::assertSame('', ColumnChanges::position(null)->toString());
    }

    public function testDefaultWritesSignedLiteralsWithoutParentheses(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ALTER n SET DEFAULT -5');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(ColumnDefaultAssignment::class, $alteration);
        self::assertSame('- 5', ColumnChanges::default($alteration->default)->toString());
    }

    public function testIndexWritesTheTypeAfterTheKeys(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ADD INDEX ix USING HASH (n)');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(AddIndex::class, $alteration);
        self::assertSame('INDEX `ix`(`n`) USING HASH', ColumnChanges::index($alteration->index)->toString());
    }

    public function testNameQuotesIdentifiers(): void
    {
        self::assertSame('`a``b`', ColumnChanges::name('a`b')->toString());
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function providerAlterations(): array
    {
        return [
            'add column after' => ['ALTER TABLE t ADD COLUMN c INT AFTER id', ['ADD COLUMN `c` integer AFTER `id`']],
            'add column with constraints' => ['ALTER TABLE t ADD COLUMN c INT NOT NULL UNIQUE PRIMARY KEY', ['ADD COLUMN `c` integer NOT NULL UNIQUE PRIMARY KEY']],
            'add columns' => ['ALTER TABLE t ADD COLUMN (c INT, d INT, PRIMARY KEY (c), UNIQUE (d), INDEX ix (d), FULLTEXT INDEX iy (c))', ['ADD COLUMN(`c` integer, `d` integer, PRIMARY KEY(`c`), UNIQUE(`d`), INDEX `ix`(`d`), FULLTEXT INDEX `iy`(`c`))']],
            'change column' => ['ALTER TABLE t CHANGE COLUMN n m INT FIRST', ['CHANGE COLUMN `n` `m` integer FIRST']],
            'modify column' => ['ALTER TABLE t MODIFY COLUMN n BIGINT AFTER id', ['MODIFY COLUMN `n` bigint AFTER `id`']],
            'drop column' => ['ALTER TABLE t DROP COLUMN n', ['DROP COLUMN `n`']],
            'set literal default' => ['ALTER TABLE t ALTER n SET DEFAULT 5', ['ALTER COLUMN `n` SET DEFAULT 5']],
            'set positive default' => ['ALTER TABLE t ALTER n SET DEFAULT +5', ['ALTER COLUMN `n` SET DEFAULT + 5']],
            'set introduced default' => ["ALTER TABLE t ALTER n SET DEFAULT _utf8mb4'x'", ["ALTER COLUMN `n` SET DEFAULT _utf8mb4 'x'"]],
            'set temporal default' => ["ALTER TABLE t ALTER n SET DEFAULT DATE '2020-01-01'", ["ALTER COLUMN `n` SET DEFAULT DATE '2020-01-01'"]],
            'set expression default' => ['ALTER TABLE t ALTER n SET DEFAULT (1+2)', ['ALTER COLUMN `n` SET DEFAULT((1 + 2))']],
            'drop default' => ['ALTER TABLE t ALTER n DROP DEFAULT', ['ALTER COLUMN `n` DROP DEFAULT']],
            'column invisible' => ['ALTER TABLE t ALTER n SET INVISIBLE', ['ALTER COLUMN `n` SET INVISIBLE']],
            'column visible' => ['ALTER TABLE t ALTER n SET VISIBLE', ['ALTER COLUMN `n` SET VISIBLE']],
            'rename column' => ['ALTER TABLE t RENAME COLUMN n TO m', ['RENAME COLUMN `n` TO `m`']],
            'add unnamed fulltext index' => ["ALTER TABLE t ADD FULLTEXT INDEX (n) COMMENT 'x'", ["ADD FULLTEXT INDEX(`n`) COMMENT 'x'"]],
            'add spatial index' => ['ALTER TABLE t ADD SPATIAL INDEX sx (n)', ['ADD SPATIAL INDEX `sx`(`n`)']],
            'add constraint' => ['ALTER TABLE t ADD CONSTRAINT ck CHECK (n > 0)', ['ADD CONSTRAINT `ck` CHECK ((`n` > 0))']],
            'drop index' => ['ALTER TABLE t DROP INDEX ix', ['DROP INDEX `ix`']],
            'index invisible' => ['ALTER TABLE t ALTER INDEX ix INVISIBLE', ['ALTER INDEX `ix` INVISIBLE']],
            'check not enforced' => ['ALTER TABLE t ALTER CHECK ck NOT ENFORCED', ['ALTER CHECK `ck` NOT ENFORCED']],
            'constraint enforced' => ['ALTER TABLE t ALTER CONSTRAINT ck ENFORCED', ['ALTER CONSTRAINT `ck` ENFORCED']],
            'rename index' => ['ALTER TABLE t RENAME INDEX a TO b', ['RENAME INDEX `a` TO `b`']],
        ];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerAlterations')]
    public function testWriteWritesEachAlteration(string $sql, array $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind($sql);
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertSame($expected, array_map(static fn ($alteration): string => ColumnChanges::write($alteration)?->toString() ?? '', $statement->alterations));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerDefaults(): array
    {
        return [
            'negated column' => ['SELECT -n FROM t', '((- `n`))'],
            'bit inverted literal' => ['SELECT ~5 FROM t', '((~ 5))'],
            'literal' => ['SELECT 5 FROM t', '5'],
            'introduced literal' => ["SELECT _utf8mb4'x' FROM t", "_utf8mb4 'x'"],
            'temporal literal' => ["SELECT DATE '2020-01-01' FROM t", "DATE '2020-01-01'"],
            'column' => ['SELECT n FROM t', '(`n`)'],
        ];
    }

    #[DataProvider('providerDefaults')]
    public function testDefaultParenthesizesAnythingButALiteral(string $sql, string $expected): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertSame($expected, ColumnChanges::default($query->outputs[0]->expression)->toString());
    }
}

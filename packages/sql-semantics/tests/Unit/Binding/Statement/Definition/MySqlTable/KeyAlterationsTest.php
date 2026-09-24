<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\MySqlTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\MySqlTable\KeyAlterations;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Key\AddConstraint;
use SqlSemantics\Model\Definition\MySqlTable\Key\AddIndex;
use SqlSemantics\Model\Definition\MySqlTable\Key\KeyKind;
use SqlSemantics\Model\Definition\MySqlTable\Key\RenameIndex;
use SqlSemantics\Model\Definition\MySqlTable\Key\SetConstraintEnforcement;
use SqlSemantics\Model\Definition\MySqlTable\Table\TableCommand;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Schema\Constraint\PrimaryKey;
use SqlSemantics\SchemaBuilder;

#[CoversClass(KeyAlterations::class)]
#[Medium]
final class KeyAlterationsTest extends TestCase
{
    public function testAddReadsAConstraintOrAnIndex(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ADD UNIQUE KEY (n), ADD SPATIAL INDEX sp (n)');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertInstanceOf(AddConstraint::class, $statement->alterations[0]);
        self::assertInstanceOf(AddIndex::class, $statement->alterations[1]);
    }

    public function testConstraintsReadsAnUnnamedConstraintKeyword(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ADD CONSTRAINT PRIMARY KEY (id)');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(AddConstraint::class, $alteration);
        self::assertInstanceOf(PrimaryKey::class, $alteration->constraint);
        self::assertNull($alteration->constraint->name);
    }

    public function testIndexesRecordTheAlteredTable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE db.t(id INT, n INT)')))->bind('ALTER TABLE db.t ADD INDEX ix (n)');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(AddIndex::class, $alteration);
        self::assertSame(['db', 't'], $alteration->index->table);
    }

    public function testDropReadsThePrimaryKey(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t DROP PRIMARY KEY');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertSame([TableCommand::DropPrimaryKey], $statement->alterations);
    }

    public function testAlterReadsCheckEnforcement(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ALTER CHECK c NOT ENFORCED');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(SetConstraintEnforcement::class, $alteration);
        self::assertSame(KeyKind::Check, $alteration->kind);
    }

    public function testRenameReadsIndexNames(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t RENAME INDEX a TO b');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(RenameIndex::class, $alteration);
        self::assertSame('b', $alteration->newName);
    }

    public function testKindReadsTheAddressedKind(): void
    {
        self::assertSame(KeyKind::ForeignKey, KeyAlterations::kind((new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t DROP FOREIGN KEY f')->find('alter_list_item')[0]));
        self::assertNull(KeyAlterations::kind((new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t DROP PRIMARY KEY')->find('alter_list_item')[0]));
    }
}

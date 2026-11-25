<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\ColumnKind;
use SqlSemantics\Platform\MySql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\Varchar;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(SourceBinding::class)]
#[Medium]
final class SourceBindingTest extends TestCase
{
    public function testReferenceAndSourceKeepTheBoundColumnName(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE ir_fixture.t (A INTEGER, b INTEGER)');
        $operation = $semantics->inspect('SELECT a FROM ir_fixture.t', [$table]);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $result = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $result);

        $binding = new SourceBinding($operation->binding(), $select->from);
        self::assertSame($select->from, $binding->relation());
        self::assertSame(0, $binding->reference($result->expression)->slot);
        self::assertSame('A', $binding->source()?->columns[0]->value);
    }

    public function testSourceIsOptional(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1');

        self::assertNull((new SourceBinding($operation->binding(), null))->source());
    }

    public function testRelationReturnsTheBoundOccurrence(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1');

        self::assertNull((new SourceBinding($operation->binding(), null))->relation());
    }

    public function testWriteTargetUsesTheDeclaredColumnSpellingAndOrder(): void
    {
        $semantics = new Inspection();
        $declaration = $semantics->inspect('CREATE TABLE app.t (id INTEGER, payload INTEGER)');
        $operation = $semantics->inspect('SELECT id FROM app.t', [$declaration]);
        self::assertInstanceOf(Select::class, $operation->statement);
        $binding = new SourceBinding($operation->binding(), $operation->statement->from);
        $target = $binding->writeTarget(['payload', 'id']);

        self::assertSame('payload', $target->columns[0]->value);
        self::assertSame('id', $target->columns[1]->value);
    }

    public function testSlotForKeepsTheIdentityOfADeclaredColumn(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE app.t (id INTEGER)');
        $operation = $inspection->inspect('SELECT id FROM app.t', [$table]);
        self::assertInstanceOf(Select::class, $operation->statement);
        $binding = new SourceBinding($operation->binding(), $operation->statement->from);
        [$column] = $binding->writableColumn('id');
        $before = $binding->slotFor($column);

        self::assertSame($before->slot, $binding->slotFor($column)->slot);
        self::assertSame(0, $before->occurrence);
    }

    public function testWritableColumnRejectsAnIneligibleType(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE app.t (a TEXT)');
        $operation = $inspection->inspect('SELECT a FROM app.t', [$table]);
        self::assertInstanceOf(Select::class, $operation->statement);
        $binding = new SourceBinding($operation->binding(), $operation->statement->from);
        $this->expectExceptionMessage('ordinary native VARCHAR');

        $binding->writableColumn('a');
    }

    public function testResolvedReadsTheDeclarationSelectedByTheWildcard(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE app.t (id INTEGER, amount INTEGER)');
        $operation = $inspection->inspect('SELECT * FROM app.t', [$table]);
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $binding = $operation->binding();
        $fields = $binding->facts->query($statement)->fields();
        self::assertNotNull($fields);
        $resolution = $fields->at(1)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        $source = new SourceBinding($binding, $statement->from);
        self::assertSame(0, $source->resolved($resolution)->slot);
        self::assertSame('amount', $source->source()?->columns[0]->value);
    }

    public function testMergedHeadingKeepsTheDeclaredSpellingInOrder(): void
    {
        $mysql = new Inspection();
        $table = $mysql->inspect('CREATE TABLE app.t (Lane INT, ID INT)');
        $query = $mysql->inspect('SELECT ID FROM app.t', [$table]);
        self::assertInstanceOf(Select::class, $query->statement);
        $binding = new SourceBinding($query->binding(), $query->statement->from);

        $heading = $binding->mergedHeading();

        self::assertSame(['lane', 'id'], array_keys($heading));
        self::assertSame('Lane', $heading['lane']->name->value);
        self::assertSame('ID', $heading['id']->name->value);
    }

    public function testProjectedAndDeclaredKeepOneExactTypedPosition(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE app.t (label VARCHAR(32) COLLATE utf8mb4_bin)');
        $operation = $inspection->inspect('SELECT label, app.t.label FROM app.t', [$table]);
        self::assertInstanceOf(Select::class, $operation->statement);
        $binding = $operation->binding();
        $facts = $binding->facts->query($operation->statement)->fields();
        self::assertNotNull($facts);
        $first = $facts->at(0)->resolution;
        $second = $facts->at(1)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $first);
        self::assertInstanceOf(ResolvedColumn::class, $second);
        $source = new SourceBinding($binding, $operation->statement->from);
        $a = $source->projected($first);
        $b = $source->projected($second);

        self::assertInstanceOf(TextColumnReference::class, $a);
        self::assertEquals($a, $b);
        self::assertSame($source->declared($first), $source->declared($second));
        self::assertInstanceOf(Varchar::class, $source->source()?->columnTypes[0]);
    }

    public function testDeclaredResolvesTheVisiblePhysicalColumn(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE app.t (id INT)');
        $operation = $inspection->inspect('SELECT id FROM app.t', [$table]);
        self::assertInstanceOf(Select::class, $operation->statement);
        $binding = $operation->binding();
        $fields = $binding->facts->query($operation->statement)->fields();
        self::assertNotNull($fields);
        $resolution = $fields->at(0)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);

        self::assertSame('id', (new SourceBinding($binding, $operation->statement->from))->declared($resolution)->name->value);
    }

    public function testAllocateRecordsOneSignedTypeAtTheChosenPosition(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE app.t (amount INT)');
        $operation = $inspection->inspect('SELECT amount FROM app.t', [$table]);
        self::assertInstanceOf(Select::class, $operation->statement);
        $binding = new SourceBinding($operation->binding(), $operation->statement->from);
        [$column] = $binding->writableColumn('amount');

        self::assertSame(0, $binding->allocate($column, ColumnKind::SignedInt));
        self::assertSame([ColumnKind::SignedInt], $binding->source()?->columnTypes);
    }

    public function testSignedKindRetainsRequiredAndNullableDeclarations(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE app.t (required_id INT NOT NULL, optional_id INT NULL)');
        $query = $inspection->inspect('SELECT required_id, optional_id FROM app.t', [$table]);
        self::assertInstanceOf(Select::class, $query->statement);
        $binding = new SourceBinding($query->binding(), $query->statement->from);

        self::assertSame(ColumnKind::RequiredSignedInt, $binding->signedKind($binding->writeDeclaration('required_id')));
        self::assertSame(ColumnKind::SignedInt, $binding->signedKind($binding->writeDeclaration('optional_id')));
    }

    public function testFieldPreservesTheNativeVarcharKind(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE app.t (label VARCHAR(8) COLLATE utf8mb4_bin)');
        $query = $inspection->inspect('SELECT label FROM app.t', [$table]);
        self::assertInstanceOf(Select::class, $query->statement);
        $item = $query->statement->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);
        $source = new SourceBinding($query->binding(), $query->statement->from);

        self::assertInstanceOf(TextColumnReference::class, $source->field($item->expression));
    }

    public function testWriteDeclarationUsesTheResolvedPhysicalName(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE app.t (ID INT)');
        $query = $inspection->inspect('SELECT ID FROM app.t', [$table]);
        self::assertInstanceOf(Select::class, $query->statement);
        $binding = new SourceBinding($query->binding(), $query->statement->from);

        self::assertSame('ID', $binding->writeDeclaration('id')->name->value);
    }

    public function testSignedWriteKeepsTheExactDeclaration(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE app.t (id INT)');
        $query = $inspection->inspect('SELECT id FROM app.t', [$table]);
        self::assertInstanceOf(Select::class, $query->statement);
        $binding = new SourceBinding($query->binding(), $query->statement->from);
        $column = $binding->writeDeclaration('id');

        self::assertSame($column, $binding->signedWrite($column));
    }

    public function testUpdateFieldPreservesTheNativeTargetRole(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE app.t (label VARCHAR(8) COLLATE utf8mb4_bin NOT NULL)');
        $query = $inspection->inspect('SELECT label FROM app.t', [$table]);
        self::assertInstanceOf(Select::class, $query->statement);
        $binding = new SourceBinding($query->binding(), $query->statement->from);

        self::assertInstanceOf(TextColumnReference::class, $binding->updateField('label'));
    }
}

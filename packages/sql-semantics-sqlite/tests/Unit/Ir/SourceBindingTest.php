<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\Coalesce;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Rows\LocalSourceLowering;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\Sqlite\Statement\Expression\TruthWord;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertRows;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Update;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithQuery;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(SourceBinding::class)]
#[Small]
final class SourceBindingTest extends TestCase
{
    public function testReferenceAndSourceKeepTheBoundColumnName(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (A INTEGER, b INTEGER)');
        $operation = $semantics->inspect('SELECT a FROM t', [$table]);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $result = $select->columns[0];
        self::assertInstanceOf(ResultColumn::class, $result);

        $binding = new SourceBinding($operation->binding(), $select->from);
        self::assertSame(0, $binding->reference($result->expression)->slot);
        self::assertSame('A', $binding->source()?->columns[0]->value);
    }

    public function testSourceIsOptional(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1');

        self::assertNull((new SourceBinding($operation->binding(), null))->source());
    }

    public function testSlotIsStableWithinTheOccurrence(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (A INTEGER, b INTEGER)');
        $operation = $semantics->inspect('SELECT a FROM t', [$table]);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $binding = new SourceBinding($operation->binding(), $select->from);
        $column = $table->declarations()[0]->columns[1];

        self::assertSame(0, $binding->slot(0, $column));
        self::assertSame(0, $binding->slot(0, $column));
        self::assertSame('b', $binding->source()?->columns[0]->value);
    }

    public function testWriteResolvesDifferentSpellingsToTheSameDeclaredSlot(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (A INTEGER, b INTEGER)');
        $operation = $semantics->inspect('UPDATE t SET a = b', [$table]);
        self::assertInstanceOf(Update::class, $operation->statement);
        $binding = new SourceBinding($operation->binding(), $operation->statement->target);

        self::assertSame(0, $binding->write(new Name('a')));
        self::assertSame(0, $binding->write(new Name('A')));
        self::assertSame('A', $binding->source()?->columns[0]->value);
    }

    public function testResolvedReadsTheDeclarationSelectedByTheWildcard(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE main.t (id INTEGER, amount INTEGER)');
        $operation = $inspection->inspect('SELECT * FROM main.t', [$table]);
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

    public function testImplicitWritesKeepsDeclaredWritableOrder(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE t (first INTEGER, computed AS (first + 1), last BLOB)');
        $operation = $inspection->inspect('INSERT INTO t VALUES (3, 7)', [$table]);
        self::assertInstanceOf(InsertRows::class, $operation->statement);
        $binding = new SourceBinding($operation->binding(), $operation->statement->into->target);
        $writes = $binding->implicitWrites();
        $target = $binding->source();

        self::assertSame([0, 1], array_map(static fn (ColumnReference $write): int => $write->slot, $writes));
        self::assertNotNull($target);
        self::assertSame('first', $target->columns[0]->value);
        self::assertSame('last', $target->columns[1]->value);
    }

    public function testConstantTruthDistinguishesAnUnshadowedWord(): void
    {
        $operation = (new Inspection())->inspect('SELECT FALSE');
        self::assertInstanceOf(Select::class, $operation->statement);
        $column = $operation->statement->columns[0];
        self::assertInstanceOf(ResultColumn::class, $column);
        self::assertInstanceOf(TruthWord::class, $column->expression);

        self::assertFalse((new SourceBinding($operation->binding(), null))->constantTruth($column->expression));
    }

    public function testInputExportsTheSameDefinitionAtEachResolvedUse(): void
    {
        $operation = (new Inspection())->inspect('WITH c(id) AS (VALUES (3)) SELECT a.id FROM c a JOIN c b');
        self::assertInstanceOf(WithQuery::class, $operation->statement);
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement->body);
        $bound = $operation->binding();
        $local = (new LocalSourceLowering())->lower($bound, $statement);
        $slots = new SourceBinding($bound, $statement->body->from, localDefinition: [$statement->with->tables[0], $local]);

        self::assertSame($local, $slots->input(0));
        self::assertSame($local, $slots->input(1));
    }

    public function testLocalSlotKeepsTheOrdinalAndTheOccurrence(): void
    {
        $operation = (new Inspection())->inspect('WITH c(first, second) AS (VALUES (1,2)) SELECT second FROM c');
        $result = $operation->binding()->facts->output?->fields()?->at(0)->expression;
        self::assertNotNull($result);
        $resolution = $operation->binding()->facts->scalar($result)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        $names = [new Identifier('first'), new Identifier('second')];
        $slots = new SourceBinding($operation->binding(), null);

        self::assertEquals(new ColumnReference(1, 4), $slots->localSlot($resolution, $names, 4));
    }

    public function testScalarValueUsesTheCombinedFieldForAnUnqualifiedName(): void
    {
        $sqlite = new Inspection();
        $left = $sqlite->inspect('CREATE TABLE a (key TEXT)');
        $right = $sqlite->inspect('CREATE TABLE b (key INT)');
        $operation = $sqlite->inspect('SELECT key FROM a FULL JOIN b USING (key)', [$left, $right]);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $selected = $select->columns[0];
        self::assertInstanceOf(ResultColumn::class, $selected);

        $binding = new SourceBinding($operation->binding(), $select->from);

        self::assertInstanceOf(Coalesce::class, $binding->scalarValue($selected->expression));
    }

    public function testValueResolvesBothOrderedInputsOfAMergedFullField(): void
    {
        $sqlite = new Inspection();
        $left = $sqlite->inspect('CREATE TABLE a (key TEXT)');
        $right = $sqlite->inspect('CREATE TABLE b (key INT)');
        $operation = $sqlite->inspect('SELECT * FROM a FULL JOIN b USING (key)', [$left, $right]);
        self::assertInstanceOf(Select::class, $operation->statement);
        $field = $operation->field(0);
        self::assertInstanceOf(ResolvedColumn::class, $field->resolution);
        $binding = new SourceBinding($operation->binding(), $operation->statement->from);
        $value = $binding->value($field->resolution);

        self::assertInstanceOf(Coalesce::class, $value);
        self::assertEquals(new ColumnReference(0, 0), $value->arguments[0]->expression);
        self::assertEquals(new ColumnReference(0, 1), $value->arguments[1]->expression);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Definition\TableShapes;
use SqlSemantics\Platform\Sqlite\Rules\Query\Projection;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Query\TableStar;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRelation;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OpenStar;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Projection::class)]
#[Medium]
final class ProjectionTest extends TestCase
{
    public function testItemsNamesEveryKindOfResultColumn(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $select = $semantics->analyze('SELECT a, (b) AS x, a + 1, rowid, *, t.* FROM t')->statement;
        self::assertInstanceOf(Select::class, $select);
        $from = $select->from;
        self::assertNotNull($from);
        $table = $create->declarations()[0];
        $shapes = new TableShapes();
        $shape = $shapes->shape($table);
        $derivation = new Derivation($semantics->context([$create]));
        $relation = new VisibleRelation($from, $shape, null, new QualifiedName(new Name('t')), [], $shapes->implicit($table, $shape));

        $items = (new Projection())->items($select->columns, $derivation, new Environment($derivation->context, null, [$relation]));

        self::assertSame(['a', 'x', null, 'rowid', 'a', 'b', 'a', 'b'], array_map(static fn (Field|OpenStar $item): ?string => $item instanceof Field ? $item->name?->value : '*', $items));
        self::assertSame(range(0, 7), array_map(static fn (Field|OpenStar $item): ?int => $item instanceof Field ? $item->position : null, $items));
        self::assertInstanceOf(Field::class, $items[1]);
        self::assertSame($table->columns[1], $items[1]->column());
        self::assertInstanceOf(Field::class, $items[4]);
        self::assertSame($shape->slots[0], $items[4]->slot->origin);
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testItemsReportsAStarWithoutInputAndAQualifierThatNamesNoRelation(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $derivation = new Derivation($semantics->context([]));
        $relation = new VisibleRelation(new TableInput(new QualifiedName(new Name('t'))), new RowShape([new OutputSlot(new Name('a'), new Known(Storage::Integer), Nullability::NotNull)]), new Name('x'));

        $bare = (new Projection())->items([new Star()], $derivation, new Environment($derivation->context));
        $qualified = (new Projection())->items([new TableStar(new Name('t')), new TableStar(new Name('x'))], $derivation, new Environment($derivation->context, null, [$relation]));

        self::assertSame([], $bare);
        self::assertSame(MisuseRule::StarWithoutTables->value, $derivation->facts()->diagnostics[0]->message());
        self::assertInstanceOf(MissingTable::class, $derivation->facts()->diagnostics[1]);
        self::assertSame('t', $derivation->facts()->diagnostics[1]->name->name->value);
        self::assertCount(1, $qualified);
        self::assertInstanceOf(Field::class, $qualified[0]);
        self::assertSame('a', $qualified[0]->name?->value);
    }

    public function testItemsReportsARowValueResultColumn(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $select = $semantics->analyze('SELECT (1, 2)')->statement;
        self::assertInstanceOf(Select::class, $select);
        $derivation = new Derivation($semantics->context([]));

        $items = (new Projection())->items($select->columns, $derivation, new Environment($derivation->context));

        self::assertCount(1, $items);
        self::assertSame(MisuseRule::TooManyValueColumns->value, $derivation->facts()->diagnostics[0]->message());
    }

    public function testExpandSkipsTheHiddenColumnsOnlyWhenMerged(): void
    {
        $input = new TableInput(new QualifiedName(new Name('t')));
        $slots = [new OutputSlot(new Name('a'), new Known(Storage::Integer), Nullability::NotNull), new OutputSlot(new Name('b'), new Known(Storage::Text), Nullability::Nullable)];
        $relation = new VisibleRelation($input, new RowShape($slots), null, null, [1]);
        $seed = [new Field(0, new OutputSlot(new Name('z'), new Known(Storage::Blob), Nullability::Nullable))];

        $merged = (new Projection())->expand($seed, $relation, true);
        $all = (new Projection())->expand($seed, $relation, false);

        self::assertCount(2, $merged);
        self::assertInstanceOf(Field::class, $merged[1]);
        self::assertSame('a', $merged[1]->name?->value);
        self::assertSame(1, $merged[1]->position);
        self::assertCount(3, $all);
        self::assertInstanceOf(Field::class, $all[2]);
        self::assertSame('b', $all[2]->name?->value);
        self::assertSame($slots[1], $all[2]->slot->origin);
        self::assertInstanceOf(ResolvedColumn::class, $all[2]->resolution);
        self::assertSame($input, $all[2]->resolution->relation);
        self::assertSame($slots[1], $all[2]->resolution->slot);
    }

    public function testExpandAppendsAnOpenStarForAnIncompleteRelation(): void
    {
        $missing = new UndeclaredRelation(new QualifiedName(new Name('t')));
        $relation = new VisibleRelation(new TableInput(new QualifiedName(new Name('t'))), new RowShape([new OutputSlot(new Name('a'), new Known(Storage::Integer), Nullability::NotNull)], [$missing]));

        $items = (new Projection())->expand([], $relation, true);

        self::assertCount(2, $items);
        self::assertInstanceOf(Field::class, $items[0]);
        self::assertInstanceOf(OpenStar::class, $items[1]);
        self::assertSame([$missing], $items[1]->missing);
    }

    public function testNamedFixesTheNameOfAColumnReferenceOnly(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->analyze('SELECT (t.a), oid, b AS x, 1 + 1, "zz", true, nosuch FROM t', [$create]);
        $select = $query->statement;
        self::assertInstanceOf(Select::class, $select);
        $projection = new Projection();
        $columns = array_values(array_filter($select->columns, static fn (object $column): bool => $column instanceof ResultColumn));

        self::assertSame('a', $projection->named($columns[0], $query->field(0)->resolution)?->value);
        self::assertSame('rowid', $projection->named($columns[1], $query->field(1)->resolution)?->value);
        self::assertSame('b', $projection->named($columns[2], $query->field(2)->resolution)?->value);
        self::assertNull($projection->named($columns[3], $query->field(3)->resolution));
        self::assertNull($projection->named($columns[4], $query->field(4)->resolution));
        self::assertNull($projection->named($columns[5], $query->field(5)->resolution));
        self::assertSame('nosuch', $projection->named($columns[6], $query->field(6)->resolution)?->value);
    }

    public function testNamedUsesTheNameOfAnAliasTarget(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT 1 AS x ORDER BY (x)');
        $select = $query->statement;
        self::assertInstanceOf(Select::class, $select);
        $term = $select->orderBy[0]->expression;

        self::assertSame('x', (new Projection())->named(new ResultColumn($term), $query->facts->scalar($term)->resolution)?->value);
    }
}

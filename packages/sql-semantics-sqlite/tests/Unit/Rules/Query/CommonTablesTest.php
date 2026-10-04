<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Query\CommonTables;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityMismatch;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithQuery;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(CommonTables::class)]
#[Medium]
final class CommonTablesTest extends TestCase
{
    public function testBindMakesEveryTableVisibleWhateverTheWrittenOrder(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $statement = $semantics->analyze("WITH d(y) AS (SELECT x FROM c), c AS (SELECT 1 AS x, 'z') SELECT * FROM d")->statement;
        self::assertInstanceOf(WithQuery::class, $statement);
        $derivation = new Derivation($semantics->context([]));

        $environment = (new CommonTables())->bind($statement->with, $derivation, $derivation->environment());

        self::assertCount(2, $environment->commonTables);
        self::assertSame('c', $environment->commonTables[0]->name->value);
        self::assertSame('d', $environment->commonTables[1]->name->value);
        $d = $environment->commonTable(new Name('d'));
        $c = $environment->commonTable(new Name('c'));
        self::assertNotNull($d);
        self::assertNotNull($c);
        self::assertSame($statement->with->tables[0], $d->definition);
        self::assertSame('y', $d->shape->slots[0]->name?->value);
        self::assertSame(['x', null], array_map(static fn (OutputSlot $slot): ?string => $slot->name?->value, $c->shape->slots));
        self::assertSame([], $environment->relations);
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testBindReportsARepeatedNameAndACircularReference(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $repeated = $semantics->analyze('WITH c AS (SELECT 1), C AS (SELECT 2) SELECT * FROM c')->statement;
        $circular = $semantics->analyze('WITH c AS (SELECT * FROM d), d AS (SELECT * FROM c) SELECT * FROM c')->statement;
        self::assertInstanceOf(WithQuery::class, $repeated);
        self::assertInstanceOf(WithQuery::class, $circular);
        $first = new Derivation($semantics->context([]));
        $second = new Derivation($semantics->context([]));

        (new CommonTables())->bind($repeated->with, $first, $first->environment());
        (new CommonTables())->bind($circular->with, $second, $second->environment());

        self::assertSame([MisuseRule::DuplicateCommonTable->value], array_map(static fn (object $problem): string => $problem->message(), $first->facts()->diagnostics));
        self::assertSame(MisuseRule::CircularReference->value, $second->facts()->diagnostics[0]->message());
        self::assertInstanceOf(MissingTable::class, $second->facts()->diagnostics[1]);
        self::assertCount(2, $second->facts()->diagnostics);
    }

    public function testBindTypesARecursiveReferenceAsAnyStorageClass(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $statement = $semantics->analyze('WITH RECURSIVE c(n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM c) SELECT * FROM c')->statement;
        self::assertInstanceOf(WithQuery::class, $statement);
        $derivation = new Derivation($semantics->context([]));

        $environment = (new CommonTables())->bind($statement->with, $derivation, $derivation->environment());
        $slot = $environment->commonTables[0]->shape->slots[0];

        self::assertSame('n', $slot->name?->value);
        self::assertInstanceOf(Choice::class, $slot->type);
        self::assertSame([Storage::Integer, Storage::Real], $slot->type->alternatives);
        self::assertSame(Nullability::Nullable, $slot->nullability);
    }

    public function testUsesAnswersTheUnqualifiedTableNamesFolded(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $statement = $semantics->analyze('WITH c AS (SELECT (SELECT 1 FROM Nested) FROM T JOIN main.u, v AS w) SELECT * FROM c')->statement;
        self::assertInstanceOf(WithQuery::class, $statement);

        $uses = (new CommonTables())->uses($statement->with->tables[0]);

        self::assertEqualsCanonicalizing(['nested', 't', 'v'], array_keys($uses));
        self::assertSame([true, true, true], array_values($uses));
    }

    public function testProvisionalHasOneSlotPerListedColumnThatCanHoldAnything(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $statement = $semantics->analyze('WITH c(x, y) AS (SELECT 1, 2), d AS (SELECT 3) SELECT * FROM c')->statement;
        self::assertInstanceOf(WithQuery::class, $statement);

        $listed = (new CommonTables())->provisional($statement->with->tables[0]);
        $unlisted = (new CommonTables())->provisional($statement->with->tables[1]);

        self::assertSame(['x', 'y'], array_map(static fn (OutputSlot $slot): ?string => $slot->name?->value, $listed->slots));
        self::assertInstanceOf(Choice::class, $listed->slots[0]->type);
        self::assertSame(Storage::cases(), $listed->slots[0]->type->alternatives);
        self::assertSame(Nullability::Nullable, $listed->slots[1]->nullability);
        self::assertTrue($listed->complete());
        self::assertSame([], $unlisted->slots);
    }

    public function testShapeRenamesTheColumnsByTheListAndReportsAnotherLength(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze("WITH c(x, y, z) AS (SELECT 1 AS a, 'b') SELECT * FROM c", []);
        $statement = $query->statement;
        self::assertInstanceOf(WithQuery::class, $statement);
        $table = $statement->with->tables[0];
        $derivation = new Derivation($semantics->context([]));

        $shape = (new CommonTables())->shape($table, $query->facts->query($table->query), $derivation);

        self::assertSame(['x', 'y', 'z'], array_map(static fn (OutputSlot $slot): ?string => $slot->name?->value, $shape->slots));
        self::assertInstanceOf(Known::class, $shape->slots[0]->type);
        self::assertSame(Storage::Integer, $shape->slots[0]->type->descriptor);
        $origin = $shape->slots[0]->origin;
        self::assertNotNull($origin);
        self::assertSame('a', $origin->name?->value);
        self::assertSame($query->facts->query($table->query)->shape->slots[0], $origin->origin);
        self::assertInstanceOf(Choice::class, $shape->slots[2]->type);
        self::assertSame(Nullability::Nullable, $shape->slots[2]->nullability);
        self::assertInstanceOf(ArityMismatch::class, $derivation->facts()->diagnostics[0]);
        self::assertSame(ArityRule::CommonTableColumns, $derivation->facts()->diagnostics[0]->rule);
        self::assertSame('A common table has 2 values for 3 columns.', $derivation->facts()->diagnostics[0]->message());
    }

    public function testShapeKeepsTheQueryNamesWithoutAListAndReportsADecoratedName(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('WITH c AS (SELECT 1 AS a, 2 AS a), d(x COLLATE nocase) AS (SELECT 3) SELECT * FROM c', []);
        $statement = $query->statement;
        self::assertInstanceOf(WithQuery::class, $statement);
        $plain = $statement->with->tables[0];
        $decorated = $statement->with->tables[1];
        $derivation = new Derivation($semantics->context([]));

        $names = (new CommonTables())->shape($plain, $query->facts->query($plain->query), $derivation);
        (new CommonTables())->shape($decorated, $query->facts->query($decorated->query), $derivation);

        self::assertSame(['a', 'a:1'], array_map(static fn (OutputSlot $slot): ?string => $slot->name?->value, $names->slots));
        self::assertSame([MisuseRule::DecoratedColumnName->value], array_map(static fn (object $problem): string => $problem->message(), $derivation->facts()->diagnostics));
    }

    public function testShapeDependsOnTheMissingInputsOfAnOpenQuery(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('WITH c(x, y) AS (SELECT * FROM t) SELECT * FROM c');
        $statement = $query->statement;
        self::assertInstanceOf(WithQuery::class, $statement);
        $table = $statement->with->tables[0];
        $derivation = new Derivation($semantics->context());

        $shape = (new CommonTables())->shape($table, $query->facts->query($table->query), $derivation);

        self::assertCount(2, $shape->slots);
        self::assertInstanceOf(Dependent::class, $shape->slots[1]->type);
        self::assertSame(Nullability::Dependent, $shape->slots[1]->nullability);
        self::assertTrue($shape->complete());
        self::assertSame([], $derivation->facts()->diagnostics);
    }
}

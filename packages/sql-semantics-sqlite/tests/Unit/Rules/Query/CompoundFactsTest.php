<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Query\CompoundFacts;
use SqlSemantics\Platform\Sqlite\Statement\Query\Compound;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityMismatch;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithQuery;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Resolution\CommonBinding;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OpenStar;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(CompoundFacts::class)]
#[Medium]
final class CompoundFactsTest extends TestCase
{
    public function testDeriveCombinesTheArmsAndOrdersByTheNamesOfEveryArm(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $compound = $semantics->analyze("SELECT 1 AS a, 'x' AS b UNION SELECT NULL AS c, 2 ORDER BY c, 2 LIMIT 1")->statement;
        self::assertInstanceOf(Compound::class, $compound);
        $derivation = new Derivation($semantics->context([]));

        $fact = (new CompoundFacts())->derive($compound, $derivation, $derivation->environment());
        $facts = $derivation->facts();

        self::assertSame(['a', 'b'], array_map(static fn (object $item): ?string => $item instanceof Field ? $item->name?->value : null, $fact->projection));
        self::assertSame(Nullability::Nullable, $fact->shape->slots[0]->nullability);
        self::assertInstanceOf(Choice::class, $fact->shape->slots[1]->type);
        self::assertSame([Storage::Integer, Storage::Text], $fact->shape->slots[1]->type->alternatives);
        $first = $facts->scalar($compound->orderBy[0]->expression)->resolution;
        $second = $facts->scalar($compound->orderBy[1]->expression)->resolution;
        self::assertInstanceOf(AliasTarget::class, $first);
        self::assertSame(0, $first->field->position);
        self::assertInstanceOf(AliasTarget::class, $second);
        self::assertSame(1, $second->field->position);
        self::assertNotNull($compound->limit);
        self::assertTrue($facts->covers($compound->limit->count));
        self::assertSame([], $facts->diagnostics);
    }

    public function testDeriveReportsAnOrderingOrALimitOnAnEarlierArm(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $compound = $semantics->analyze('SELECT 1 ORDER BY 1 UNION SELECT 2 LIMIT 1 UNION SELECT 3')->statement;
        self::assertInstanceOf(Compound::class, $compound);
        $derivation = new Derivation($semantics->context([]));

        (new CompoundFacts())->derive($compound, $derivation, $derivation->environment());

        self::assertSame([MisuseRule::OrderByBeforeCompound->value, MisuseRule::LimitBeforeCompound->value], array_map(static fn (object $problem): string => $problem->message(), $derivation->facts()->diagnostics));
    }

    public function testCombinedTakesTheNamesOfTheFirstArmAndReportsAnotherWidthOnce(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze("SELECT 1 AS a UNION SELECT 'x', 2 UNION SELECT NULL, 3, 4");
        $compound = $query->statement;
        self::assertInstanceOf(Compound::class, $compound);
        $facts = [$query->facts->query($compound->first), $query->facts->query($compound->steps[0]->query), $query->facts->query($compound->steps[1]->query)];
        $derivation = new Derivation($semantics->context([]));

        $items = (new CompoundFacts())->combined($facts, $derivation);

        self::assertCount(1, $items);
        self::assertInstanceOf(Field::class, $items[0]);
        self::assertSame('a', $items[0]->name?->value);
        self::assertInstanceOf(Known::class, $items[0]->type);
        self::assertSame(Storage::Integer, $items[0]->type->descriptor);
        self::assertSame(Nullability::NotNull, $items[0]->nullability);
        self::assertSame($facts[0]->shape->slots[0], $items[0]->slot->origin);
        self::assertCount(1, $derivation->facts()->diagnostics);
        self::assertInstanceOf(ArityMismatch::class, $derivation->facts()->diagnostics[0]);
        self::assertSame(ArityRule::CompoundArms, $derivation->facts()->diagnostics[0]->rule);
        self::assertSame(2, $derivation->facts()->diagnostics[0]->actual);
    }

    public function testCombinedAnswersAnOpenStarWhenTheFirstArmIsOpen(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT * FROM t UNION SELECT 1');
        $compound = $query->statement;
        self::assertInstanceOf(Compound::class, $compound);
        $derivation = new Derivation($semantics->context());

        $items = (new CompoundFacts())->combined([$query->facts->query($compound->first), $query->facts->query($compound->steps[0]->query)], $derivation);

        self::assertCount(1, $items);
        self::assertInstanceOf(OpenStar::class, $items[0]);
        self::assertSame('the declaration of relation t', $items[0]->missing[0]->describe());
    }

    public function testCombinedDependsOnAnOpenLaterArm(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT 1 AS a UNION SELECT * FROM t');
        $compound = $query->statement;
        self::assertInstanceOf(Compound::class, $compound);

        $items = (new CompoundFacts())->combined([$query->facts->query($compound->first), $query->facts->query($compound->steps[0]->query)], new Derivation($semantics->context()));

        self::assertInstanceOf(Field::class, $items[0]);
        self::assertSame(Nullability::Dependent, $items[0]->nullability);
    }

    public function testAliasesNamesTheOutputAfterEveryArm(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT 1 AS a, 2 UNION SELECT 3 AS b, 4 AS c');
        $compound = $query->statement;
        self::assertInstanceOf(Compound::class, $compound);
        $arms = [$compound->first, $compound->steps[0]->query];
        $facts = [$query->facts->query($compound->first), $query->facts->query($compound->steps[0]->query)];
        $output = $query->facts->output;
        self::assertNotNull($output);

        $aliases = (new CompoundFacts())->aliases($arms, $facts, $output->projection);

        self::assertSame(['a', 'b', 'c'], array_map(static fn (Field $field): ?string => $field->name?->value, $aliases));
        self::assertSame([0, 0, 1], array_map(static fn (Field $field): int => $field->position, $aliases));
        self::assertSame($query->field(1)->slot, $aliases[2]->slot->origin);
        self::assertSame([], (new CompoundFacts())->aliases($arms, $facts, []));
    }

    public function testSpelledTellsANameTakenFromTheTextOfAnExpression(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT 1+1, (a), 2 AS b, * FROM (SELECT 1 AS a)');
        $select = $query->statement;
        self::assertInstanceOf(Select::class, $select);
        $facts = new CompoundFacts();

        self::assertTrue($facts->spelled($select, $query->field(0)));
        self::assertFalse($facts->spelled($select, $query->field(1)));
        self::assertFalse($facts->spelled($select, $query->field(2)));
        self::assertFalse($facts->spelled($select, $query->field(3)));
        self::assertFalse($facts->spelled(null, $query->field(0)));
    }

    public function testReboundGivesTheRecursiveReferenceTheNamesOfTheColumnListAndAnyStorageClass(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('WITH RECURSIVE c(n, true, N) AS (SELECT 1 AS x, 2, 3 UNION ALL SELECT n + 1, 2, 3 FROM c) SELECT * FROM c', []);
        $statement = $query->statement;
        self::assertInstanceOf(WithQuery::class, $statement);
        $table = $statement->with->tables[0];
        $compound = $table->query;
        self::assertInstanceOf(Compound::class, $compound);
        $outer = new Environment($query->context, null, [], [new CommonBinding($table->name, $table, new RowShape([]))]);

        $rebound = (new CompoundFacts())->rebound($compound, $query->facts->query($compound->first), $outer, new Derivation($query->context));
        $slot = $rebound->commonTables[0]->shape->slots[0];

        self::assertNotSame($outer, $rebound);
        self::assertSame($table, $rebound->commonTables[0]->definition);
        self::assertSame(['n', 'column2', 'N:1'], array_map(static fn (OutputSlot $slot): ?string => $slot->name?->value, $rebound->commonTables[0]->shape->slots));
        self::assertInstanceOf(Choice::class, $slot->type);
        self::assertSame(Storage::cases(), $slot->type->alternatives);
        self::assertSame(Nullability::Nullable, $slot->nullability);
    }

    public function testReboundNamesTheColumnsAfterTheFirstArmWithoutAColumnList(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('WITH RECURSIVE c AS (SELECT 1 AS x, 1+1 UNION ALL SELECT x + 1, 2 FROM c) SELECT * FROM c', []);
        $statement = $query->statement;
        self::assertInstanceOf(WithQuery::class, $statement);
        $table = $statement->with->tables[0];
        $compound = $table->query;
        self::assertInstanceOf(Compound::class, $compound);
        $outer = new Environment($query->context, null, [], [new CommonBinding($table->name, $table, new RowShape([]))]);

        $rebound = (new CompoundFacts())->rebound($compound, $query->facts->query($compound->first), $outer, new Derivation($query->context));

        self::assertSame('x', $rebound->commonTables[0]->shape->slots[0]->name?->value);
        self::assertSame('1+1', $rebound->commonTables[0]->shape->slots[1]->name?->value);
    }

    public function testReboundLeavesAnEnvironmentWithoutTheTableUntouched(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT 1 UNION SELECT 2');
        $compound = $query->statement;
        self::assertInstanceOf(Compound::class, $compound);
        $outer = new Environment($query->context);

        self::assertSame($outer, (new CompoundFacts())->rebound($compound, $query->facts->query($compound->first), $outer, new Derivation($query->context)));
    }
}

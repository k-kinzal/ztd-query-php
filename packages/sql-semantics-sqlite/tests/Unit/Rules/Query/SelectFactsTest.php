<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Query\SelectFacts;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(SelectFacts::class)]
#[Medium]
final class SelectFactsTest extends TestCase
{
    public function testDeriveAnswersTheOutputAndRecordsEveryClause(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $select = $semantics->analyze('SELECT a AS x, b FROM t WHERE x > 1 GROUP BY b HAVING count(*) > 1 WINDOW w AS (PARTITION BY a) ORDER BY x LIMIT 1 OFFSET 2')->statement;
        self::assertInstanceOf(Select::class, $select);
        $derivation = new Derivation($semantics->context([$create]));

        $fact = (new SelectFacts())->derive($select, $derivation, $derivation->environment());
        $facts = $derivation->facts();

        self::assertSame(['x', 'b'], array_map(static fn (object $item): ?string => $item instanceof Field ? $item->name?->value : null, $fact->projection));
        self::assertInstanceOf(Binary::class, $select->where);
        self::assertInstanceOf(AliasTarget::class, $facts->scalar($select->where->left)->resolution);
        self::assertInstanceOf(ResolvedColumn::class, $facts->scalar($select->groupBy[0])->resolution);
        self::assertNotNull($select->having);
        self::assertTrue($facts->covers($select->having));
        self::assertTrue($facts->covers($select->windows[0]->window->expressions()[0]));
        self::assertInstanceOf(AliasTarget::class, $facts->scalar($select->orderBy[0]->expression)->resolution);
        self::assertNotNull($select->limit);
        self::assertTrue($facts->covers($select->limit->count));
        self::assertNotNull($select->limit->offset);
        self::assertTrue($facts->covers($select->limit->offset));
        self::assertSame([], $facts->diagnostics);
    }

    public function testDeriveMakesInputColumnsNullableInAnAggregateSelectionWithoutGroupBy(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $having = $semantics->analyze('SELECT a FROM t HAVING 1')->statement;
        $ordered = $semantics->analyze('SELECT a FROM t ORDER BY max(b)')->statement;
        $plain = $semantics->analyze('SELECT a FROM t WHERE max(b) > 1')->statement;
        self::assertInstanceOf(Select::class, $having);
        self::assertInstanceOf(Select::class, $ordered);
        self::assertInstanceOf(Select::class, $plain);
        $derivation = new Derivation($semantics->context([$create]));

        self::assertSame(Nullability::Nullable, (new SelectFacts())->derive($having, $derivation, $derivation->environment())->shape->slots[0]->nullability);
        self::assertSame(Nullability::Nullable, (new SelectFacts())->derive($ordered, $derivation, $derivation->environment())->shape->slots[0]->nullability);
        self::assertSame(Nullability::NotNull, (new SelectFacts())->derive($plain, $derivation, $derivation->environment())->shape->slots[0]->nullability);
    }

    public function testAliasesAnswersTheFieldsWithAnExplicitAliasInOutputOrder(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->analyze('SELECT a, *, b AS x, 1 AS y, a + 1 FROM t', [$create]);
        $select = $query->statement;
        $output = $query->facts->output;
        self::assertInstanceOf(Select::class, $select);
        self::assertNotNull($output);

        $aliases = (new SelectFacts())->aliases($select, $output->projection);

        self::assertSame(['x', 'y'], array_map(static fn (Field $field): ?string => $field->name?->value, $aliases));
        self::assertSame([3, 4], array_map(static fn (Field $field): int => $field->position, $aliases));
        self::assertSame([], (new SelectFacts())->aliases($select, []));
    }

    public function testLimitDerivesTheExpressionsWithoutTheColumnsOfTheSelection(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $select = $semantics->analyze('SELECT a FROM t LIMIT a OFFSET 2')->statement;
        self::assertInstanceOf(Select::class, $select);
        $limit = $select->limit;
        self::assertNotNull($limit);
        self::assertNotNull($limit->offset);
        $derivation = new Derivation($semantics->context([$create]));

        (new SelectFacts())->limit($limit, $derivation, $derivation->environment());
        (new SelectFacts())->limit(null, $derivation, $derivation->environment());

        self::assertInstanceOf(MissingColumn::class, $derivation->facts()->scalar($limit->count)->resolution);
        self::assertSame(Nullability::NotNull, $derivation->facts()->scalar($limit->offset)->nullability);
        self::assertCount(1, $derivation->facts()->diagnostics);
        self::assertFalse($derivation->facts()->covers(new IntegerLiteral('1')));
    }
}

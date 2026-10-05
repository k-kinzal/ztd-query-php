<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Having;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rules\Query\Having\GroupedRow;
use SqlSemantics\Platform\MySql\Rules\Query\Having\HavingScope;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(HavingScope::class)]
#[Medium]
final class HavingScopeTest extends TestCase
{
    public function testEnterAddsTheGroupedRowAsAnOccurrenceWithoutColumns(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile(null, null, ParameterStyle::Native), null, [], false));
        $row = new GroupedRow([], [], true);
        $entered = (new HavingScope())->enter($derivation->environment(), $row);

        self::assertCount(1, $entered->relations);
        self::assertSame($row, $entered->relations[0]->relation);
        self::assertSame([], $entered->relations[0]->shape->slots);
        self::assertNull($entered->relations[0]->alias);
        self::assertNull($entered->relations[0]->name);
    }

    public function testGroupingAnswersTheColumnsAsTheHavingPositionSeesThem(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT NOT NULL)');
        $plain = $semantics->analyze('SELECT a AS x FROM t GROUP BY x HAVING a > 0', [$table]);
        $rollup = $semantics->analyze('SELECT a AS x FROM t GROUP BY x WITH ROLLUP HAVING a > 0', [$table]);
        $plainSelect = $plain->statement;
        $rollupSelect = $rollup->statement;
        self::assertInstanceOf(Select::class, $plainSelect);
        self::assertInstanceOf(Select::class, $rollupSelect);
        self::assertInstanceOf(Comparison::class, $plainSelect->having);
        self::assertInstanceOf(Comparison::class, $rollupSelect->having);
        $plainFact = $plain->facts->scalar($plainSelect->having->left);
        $rollupFact = $rollup->facts->scalar($rollupSelect->having->left);

        self::assertInstanceOf(ResolvedColumn::class, $plainFact->resolution);
        self::assertSame(Nullability::NotNull, $plainFact->nullability);
        self::assertInstanceOf(ResolvedColumn::class, $rollupFact->resolution);
        self::assertSame(Nullability::Nullable, $rollupFact->nullability);
        self::assertSame([], $rollup->facts->diagnostics);
    }

    public function testUndecidedAnswersTheColumnsOfIncompletelyKnownOccurrences(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $grouped = $semantics->analyze('SELECT 1 FROM u GROUP BY c HAVING c > 0');
        $selected = $semantics->analyze('SELECT a AS x FROM u HAVING a > 0');
        $missing = $semantics->analyze('SELECT a AS x FROM u HAVING b > 0');
        $groupedSelect = $grouped->statement;
        $selectedSelect = $selected->statement;
        self::assertInstanceOf(Select::class, $groupedSelect);
        self::assertInstanceOf(Select::class, $selectedSelect);
        self::assertInstanceOf(Comparison::class, $groupedSelect->having);
        self::assertInstanceOf(Comparison::class, $selectedSelect->having);

        self::assertInstanceOf(ConditionalColumn::class, $grouped->facts->scalar($groupedSelect->having->left)->resolution);
        self::assertInstanceOf(ConditionalColumn::class, $selected->facts->scalar($selectedSelect->having->left)->resolution);
        self::assertSame([], $grouped->facts->diagnostics);
        self::assertCount(1, $missing->facts->diagnostics);
        self::assertSame([], (new HavingScope())->undecided([], []));
    }

    public function testLeaveLetsTheArgumentsOfASetFunctionSeeTheColumns(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze('SELECT a FROM t HAVING MAX(c) > 0', [$semantics->analyze('CREATE TABLE t (a INT, c INT)')]);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(Comparison::class, $select->having);
        $maximum = $select->having->left;
        self::assertInstanceOf(Aggregate::class, $maximum);
        $platform = new Platform();
        $environment = (new Derivation($platform->context($platform->profile(null, null, ParameterStyle::Native), null, [], false)))->environment();

        self::assertSame([], $operation->facts->diagnostics);
        self::assertInstanceOf(ResolvedColumn::class, $operation->facts->scalar($maximum->arguments[0])->resolution);
        self::assertSame($environment, (new HavingScope())->leave($environment));
        self::assertSame([], (new HavingScope())->leave((new HavingScope())->enter($environment, new GroupedRow([], [], true)))->relations);
    }

    public function testRowAnswersTheGroupedRowOfAHavingEnvironmentOnly(): void
    {
        $platform = new Platform();
        $environment = (new Derivation($platform->context($platform->profile(null, null, ParameterStyle::Native), null, [], false)))->environment();
        $row = new GroupedRow([], [], false);

        self::assertNull((new HavingScope())->row($environment));
        self::assertSame($row, (new HavingScope())->row((new HavingScope())->enter($environment, $row)));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Result;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\Result\TemporalFields;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(TemporalFields::class)]
#[Small]
final class TemporalFieldsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field}>
     */
    public static function providerReportedFields(): iterable
    {
        foreach (['mysql-5.6.51', 'mysql-5.7.44'] as $release) {
            yield $release . ' direct expression' => [$release, 'SELECT MIN(COALESCE(d,d)) FROM t', \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::VarString];
            yield $release . ' rollup expression' => [$release, 'SELECT MAX(CAST(d AS DATE)) FROM t GROUP BY n WITH ROLLUP', \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::VarString];
            yield $release . ' stored group' => [$release, 'SELECT MIN(COALESCE(d,d)) FROM t GROUP BY n', \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::Date];
            yield $release . ' direct column' => [$release, 'SELECT MIN(d) FROM t', \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::Date];
        }
    }

    #[DataProvider('providerReportedFields')]
    public function testReportedSeparatesLegacyComputedResultsFromStoredFields(string $release, string $sql, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field $field): void
    {
        $semantics = new Semantics(Dialect::MySql, $release);
        $table = $semantics->analyze('CREATE TABLE t(d DATE,n INT)');
        $type = $semantics->analyze($sql, [$table])->field(0)->type;
        self::assertInstanceOf(Known::class, $type);
        self::assertInstanceOf(Domain::class, $type->descriptor);

        self::assertSame($field, $type->descriptor->field);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerStoredFields(): iterable
    {
        yield 'grouped field' => ['SELECT MIN(d) FROM t GROUP BY n', true];
        yield 'computed over grouped fields' => ['SELECT COALESCE(MIN(d),MIN(d)) FROM t GROUP BY n', false];
        yield 'rollup field' => ['SELECT MIN(d) FROM t GROUP BY n WITH ROLLUP', false];
        yield 'distinct computed output' => ['SELECT DISTINCT COALESCE(MIN(d),MIN(d)) FROM t GROUP BY n', true];
        yield 'ordered rollup field' => ['SELECT MIN(d) FROM t GROUP BY n WITH ROLLUP ORDER BY 1', true];
        yield 'computed ordered rollup' => ['SELECT COALESCE(MIN(d),MIN(d)) FROM t GROUP BY n WITH ROLLUP ORDER BY 1', false];
    }

    #[DataProvider('providerStoredFields')]
    public function testStoredSeparatesExpressionCollationsFromOutputFields(string $sql, bool $stored): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (d DATE,n INT)');
        $query = $semantics->analyze($sql, [$table]);
        $select = $query;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $select);
        $select = $select->query;
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(SelectExpression::class, $select->items[0]);
        $expression = $select->items[0]->expression;
        $logical = $expression->scalarInformation()->type;
        $output = $query->field(0)->type;
        self::assertInstanceOf(Known::class, $logical);
        self::assertInstanceOf(Domain::class, $logical->descriptor);
        self::assertInstanceOf(Known::class, $output);
        self::assertInstanceOf(Domain::class, $output->descriptor);

        self::assertSame($stored, (new TemporalFields())->stored($select, $expression));
        self::assertSame('latin1_swedish_ci', $logical->descriptor->collation->name);
        self::assertSame($stored, $output->descriptor->collation->bytes());
    }

    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function providerWindowReads(): iterable
    {
        yield 'earlier window' => ['SELECT MIN(d) OVER () FROM t', false, false];
        yield 'final window' => ['SELECT MIN(d) OVER () FROM t', true, true];
        yield 'grouped expression' => ['SELECT MIN(MIN(d)) OVER () FROM t GROUP BY n', false, true];
        yield 'distinct grouped expression' => ['SELECT DISTINCT MIN(MIN(d)) OVER () FROM t GROUP BY n', false, false];
        yield 'distinct rollup expression' => ['SELECT DISTINCT MIN(MIN(d)) OVER () FROM t GROUP BY n WITH ROLLUP', false, true];
    }

    #[DataProvider('providerWindowReads')]
    public function testRetainsWindowDistinguishesIntermediateFieldsAndLogicalReads(string $sql, bool $last, bool $retained): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (d DATE,n INT)');
        $query = $semantics->analyze($sql, [$table]);
        $select = $query;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $select);
        $select = $select->query;
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(SelectExpression::class, $select->items[0]);

        self::assertSame($retained, (new TemporalFields())->retainsWindow($select, $select->items[0]->expression, $last));
    }
}

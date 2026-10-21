<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Call\WeightResults;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(WeightResults::class)]
#[Small]
final class WeightResultsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<int>}>
     */
    public static function providerDomainLengths(): iterable
    {
        yield 'legacy' => ['mysql-5.7.44', [3, 8, 7, 6, 67108864]];
        yield 'modern' => ['mysql-8.4.7', [8, 8, 8, 8, 3221225469]];
    }

    /**
     * @param list<int> $lengths
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerDomainLengths')]
    public function testFactDistinguishesStoredTemporalWidthsAndJsonCapacity(string $release, array $lengths): void
    {
        $semantics = new Semantics(Dialect::MySql, $release);
        $table = $semantics->analyze('CREATE TABLE t(d DATE, dt DATETIME(6), ts TIMESTAMP(6), tm TIME(6))');
        $query = $semantics->analyze("SELECT WEIGHT_STRING(d AS CHAR(32)), WEIGHT_STRING(dt,16,1,0), WEIGHT_STRING(ts), WEIGHT_STRING(tm), WEIGHT_STRING(JSON_OBJECT('a',1)) FROM t", [$table]);
        $actual = array_map(static fn (int $index): ?int => (new \SqlSemantics\Platform\MySql\Rules\Typing\Precision())->domain($query->field($index)->type)?->length, range(0, 4));
        self::assertSame($lengths, $actual);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerStoredInputs(): iterable
    {
        yield 'field' => ['SELECT d FROM t', true];
        yield 'merged view' => ['SELECT d FROM m', false];
        yield 'materialized view' => ['SELECT d FROM s', true];
        yield 'materialized derived view' => ['SELECT d FROM (SELECT d FROM m LIMIT 1) q', true];
        yield 'merged derived field' => ['SELECT d FROM (SELECT d FROM t) q', false];
        yield 'literal' => ["SELECT DATE '2024-01-01'", false];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerStoredInputs')]
    public function testStoredDistinguishesFieldAndExpressionReferences(string $sql, bool $stored): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t(d DATE)');
        $merged = $semantics->analyze('CREATE ALGORITHM=MERGE VIEW m AS SELECT d FROM t', [$table]);
        $materialized = $semantics->analyze('CREATE ALGORITHM=TEMPTABLE VIEW s AS SELECT d FROM t', [$table]);
        $operation = $semantics->analyze($sql, [$table, $merged, $materialized]);
        $expression = $operation->field(0)->expression;
        self::assertNotNull($expression);
        self::assertSame($stored, (new WeightResults())->stored($operation->facts->scalar($expression), $operation->facts, $semantics->profile()->grammar, true));
    }

    public function testTemporalLengthPreservesStoredFractionalPrecision(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $columns = $semantics->analyze('CREATE TABLE t(d DATE, dt DATETIME(6), ts TIMESTAMP(6), tm TIME(6))')->declarations()[0]->columns;
        $lengths = array_map(static fn ($column): ?int => $column->type instanceof Domain ? (new WeightResults())->temporalLength($column->type) : null, $columns);
        self::assertSame([3, 8, 7, 6], $lengths);
    }

    public function testCharacterBytesNarrowsBeforeChoosingTheResultField(): void
    {
        $modern = (new Semantics(Dialect::MySql))->analyze("SELECT WEIGHT_STRING(_utf8mb4'abc' COLLATE utf8mb4_bin AS CHAR(1073741824)), WEIGHT_STRING(_utf8mb4'abc' COLLATE utf8mb4_bin AS CHAR(65536))");
        $legacy = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("SELECT WEIGHT_STRING(_utf8mb4'abc' COLLATE utf8mb4_bin AS CHAR(1073741824))");

        self::assertEquals(new Known(Domain::string(8, Collation::binary())), $modern->field(0)->type);
        self::assertEquals(new Known(Domain::string(196608, Collation::binary(), \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::MediumBlob)), $modern->field(1)->type);
        self::assertEquals(new Known(Domain::string(0, Collation::binary())), $legacy->field(0)->type);
    }

    public function testFactResolvesCollationCapacityWithoutShorteningItForChar(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("SELECT WEIGHT_STRING(_utf8mb4'ab' COLLATE utf8mb4_general_ci AS CHAR(1)), WEIGHT_STRING(_utf8mb4'ab' COLLATE utf8mb4_general_ci,1,1,1)");

        self::assertEquals(new Known(Domain::string(16, Collation::binary())), $operation->field(0)->type);
        self::assertEquals(new Known(Domain::string(8, Collation::binary())), $operation->field(1)->type);
    }

    public function testCapacityIncludesLongerRequestedWeightCounts(): void
    {
        self::assertSame(16, (new WeightResults())->capacity(Collation::known('utf8mb3_bin'), 6, 8));
    }

    public function testCapacityKeepsBinaryByteCounts(): void
    {
        self::assertSame(4, (new WeightResults())->capacity(Collation::binary(), 2, 4));
    }

    public function testLengthIncludesCollationLevelSeparators(): void
    {
        self::assertSame(388, (new WeightResults())->length(Domain::string(2, Collation::known('utf8mb4_0900_as_cs'))));
        self::assertSame(24, (new WeightResults())->length(Domain::string(2, Collation::known('utf8mb4_bin'))));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rules\Expression\SubqueryRows;
use SqlSemantics\Platform\MySql\Rules\Typing\Materialization;
use SqlSemantics\Platform\MySql\Statement\Expression\Tuple;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field as ResolvedField;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Missing\SessionState;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OpenStar;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(SubqueryRows::class)]
#[Medium]
final class SubqueryRowsTest extends TestCase
{
    public function testAttributeKeepsTheMySql56RowFlagSeparateFromItsNullableColumns(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.6.51');
        $operation = $semantics->analyze('SELECT (SELECT NULL,NULL) = (1,2), (SELECT NULL,NULL) = (NULL,NULL), (SELECT NULL,NULL FROM DUAL WHERE 0) = (1,2), (SELECT NULL,NULL FROM DUAL WHERE 0 UNION ALL SELECT 1,2 FROM DUAL WHERE 0) = (1,2)');

        self::assertSame(Nullability::NotNull, $operation->field(0)->nullability);
        self::assertSame(Nullability::Nullable, $operation->field(1)->nullability);
        self::assertSame(Nullability::Nullable, $operation->field(2)->nullability);
        self::assertSame(Nullability::NotNull, $operation->field(3)->nullability);
    }

    public function testTablelessDistinguishesDualFromActualRelationsAcrossUnionOperands(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $rules = new SubqueryRows();
        $tableless = $semantics->analyze('(SELECT 1 FROM DUAL WHERE 0) UNION ALL SELECT 2 HAVING 0')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $tableless);
        $tableless = $tableless->query;
        $tabled = $semantics->analyze('SELECT 1 FROM DUAL UNION ALL SELECT 2 FROM t')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $tabled);
        $tabled = $tabled->query;

        self::assertTrue($rules->tableless($tableless));
        self::assertFalse($rules->tableless($tabled));
    }

    public function testValueReadsTextualTemporalResultsAsStringsWithTheirReportedPrecision(): void
    {
        $domain = new Domain(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Time, ResolvedField::VarString, 14, 3, collation: Collation::known('latin1_swedish_ci'));
        $query = new QueryFact([new Field(0, new OutputSlot(new Name('a'), new Known($domain), Nullability::Nullable))], Comparison::AsciiInsensitive);
        $result = (new SubqueryRows())->value($query)->type;

        self::assertInstanceOf(Known::class, $result);
        self::assertInstanceOf(Domain::class, $result->descriptor);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::String, $result->descriptor->kind);
        self::assertSame(3, $result->descriptor->displayDecimals);
    }

    public function testValueIsTheOneColumnOrARow(): void
    {
        $integer = new Known(new Integral(IntegralKind::Int));
        $one = new QueryFact([new Field(0, new OutputSlot(new Name('a'), $integer, Nullability::NotNull))], Comparison::AsciiInsensitive);
        $two = new QueryFact([new Field(0, new OutputSlot(new Name('a'), $integer, Nullability::NotNull)), new Field(1, new OutputSlot(new Name('b'), $integer, Nullability::NotNull))], Comparison::AsciiInsensitive);
        $open = new QueryFact([new OpenStar([new SessionState('x')])], Comparison::AsciiInsensitive);
        $rows = new SubqueryRows();

        self::assertEquals(new ScalarFact($integer, Nullability::Nullable), $rows->value($one));
        self::assertEquals(new Known(new Tuple(2)), $rows->value($two)->type);
        self::assertInstanceOf(Dependent::class, $rows->value($open)->type);
    }

    public function testValueDropsTheDisplayWidthOfTheColumn(): void
    {
        $column = new QueryFact([new Field(0, new OutputSlot(new Name('a'), new Known(Domain::column(ResolvedField::Long, 2)), Nullability::NotNull))], Comparison::AsciiInsensitive);

        self::assertEquals(new Known(Domain::integer(ResolvedField::Long, 11)), (new SubqueryRows())->value($column)->type);
    }

    public function testValueDropsFixedFractionalMetadataForStrings(): void
    {
        $type = new Domain(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::String, ResolvedField::VarString, 20, 0, false, Collation::known('latin1_swedish_ci'));
        $query = new QueryFact([new Field(0, new OutputSlot(new Name('b'), new Known($type), Nullability::NotNull))], Comparison::AsciiInsensitive);
        $value = (new SubqueryRows())->value($query);

        self::assertEquals(new Known(Domain::string(20, $type->collation)), $value->type);
        self::assertSame(Nullability::Nullable, $value->nullability);
    }

    public function testStringPreservesDirectGroupedConcatenationMetadata(): void
    {
        $type = new Domain(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::String, ResolvedField::Blob, 1024, 0, false, Collation::known('latin1_swedish_ci'));
        $expression = new \SqlSemantics\Platform\MySql\Statement\Expression\Grouped(new \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')]));

        self::assertSame($type, (new SubqueryRows())->string($type, $expression));
        self::assertSame(Domain::NOT_FIXED, (new SubqueryRows())->string($type, null)->decimals);
    }

    public function testValueExpandsBlobColumnsThroughGroupingAndStars(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE blobs (b TEXT CHARACTER SET utf8mb4)');
        $plain = $semantics->analyze('SELECT (SELECT b FROM blobs)', [$table])->field(0)->type;
        $grouped = $semantics->analyze('SELECT (SELECT (b) FROM blobs)', [$table])->field(0)->type;
        $star = $semantics->analyze('SELECT (SELECT * FROM blobs)', [$table])->field(0)->type;

        self::assertInstanceOf(Known::class, $plain);
        self::assertInstanceOf(Domain::class, $plain->descriptor);
        self::assertSame(262140, $plain->descriptor->length);
        self::assertEquals($plain, $grouped);
        self::assertEquals($plain, $star);
    }

    public function testValueReadsTheCapacityOfAMaterializedSetColumn(): void
    {
        $domain = (new Materialization())->set(Domain::string(4096, Collation::known('utf8mb4_bin'), ResolvedField::Blob));
        $query = new QueryFact([new Field(0, new OutputSlot(new Name('q'), new Known($domain), Nullability::Nullable))], Comparison::AsciiInsensitive);

        self::assertEquals(new Known(Domain::string(262140, $domain->collation, ResolvedField::Blob)), (new SubqueryRows())->value($query)->type);
    }

    public function testStringKeepsExplicitComputedBlobMetadataSeparateFromTheExpressionBound(): void
    {
        $domain = new Domain(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::String, ResolvedField::Blob, 65535, Domain::NOT_FIXED, false, Collation::known('utf8mb4_bin'), display: 262140);

        self::assertEquals($domain, (new SubqueryRows())->string($domain, null));
    }

    public function testPredicateRetainsNullableMetadataUntilMembershipIsReduced(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t(id INT PRIMARY KEY)');
        $operation = $semantics->analyze('SELECT id IN (SELECT id FROM t), id IN (SELECT 1), id IN (SELECT 1 WHERE FALSE), id IN (SELECT 1 WHERE TRUE) FROM t', [$table]);

        self::assertSame(Nullability::Nullable, $operation->field(0)->nullability);
        self::assertSame(Nullability::NotNull, $operation->field(1)->nullability);
        self::assertSame(Nullability::Nullable, $operation->field(2)->nullability);
        self::assertSame(Nullability::NotNull, $operation->field(3)->nullability);
    }

    public function testTestCombinesTheColumnsAndReportsAWidthMismatch(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        $integer = new Known(new Integral(IntegralKind::Int));
        $two = new QueryFact([new Field(0, new OutputSlot(new Name('a'), $integer, Nullability::NotNull)), new Field(1, new OutputSlot(new Name('b'), $integer, Nullability::Nullable))], Comparison::AsciiInsensitive);
        $nullability = (new SubqueryRows())->test(new ScalarFact($integer, Nullability::NotNull), $two, $derivation);

        self::assertSame(Nullability::Nullable, $nullability);
        self::assertSame('Operand should contain 1 column(s), not 2.', $derivation->facts()->diagnostics[0]->message());
    }

    public function testTestReportsRowMembershipAsNullableEvenForConstantNotNullColumns(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT (1, 2) IN (SELECT 1, 2), 1 IN (SELECT 1)');

        self::assertSame(Nullability::Nullable, $operation->field(0)->nullability);
        self::assertSame(Nullability::NotNull, $operation->field(1)->nullability);
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function providerMembershipWidths(): iterable
    {
        foreach (['mysql-5.6.51', 'mysql-5.7.44', 'mysql-8.0.44', 'mysql-8.4.7', 'mysql-9.1.0'] as $version) {
            foreach (['IN', '= ANY', '= SOME', 'NOT IN', '<> ALL'] as $operator) {
                $wide = str_starts_with($version, 'mysql-5.') && in_array($operator, ['IN', '= ANY', '= SOME'], true);
                yield $version . ' ' . $operator => [$version, $operator, $wide ? 21 : 1];
            }
        }
    }

    #[DataProvider('providerMembershipWidths')]
    public function testPredicateRetainsTheReleaseSpecificWidth(string $version, string $operator, int $width): void
    {
        $type = (new Semantics(Dialect::MySql, $version))->analyze('SELECT (1,2) ' . $operator . ' (SELECT 1,2)')->field(0)->type;

        self::assertEquals(new Known(Domain::integer(ResolvedField::LongLong, $width)), $type);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerStoredWidths(): iterable
    {
        yield '5.6' => ['mysql-5.6.51', 21];
        yield '5.7' => ['mysql-5.7.44', 21];
        yield '8.4' => ['mysql-8.4.7', 1];
    }

    #[DataProvider('providerStoredWidths')]
    public function testStoredRetainsTheLegacyMembershipColumnWidth(string $release, int $width): void
    {
        $semantics = new Semantics(Dialect::MySql, $release);
        $table = $semantics->analyze('CREATE TABLE t(id INT PRIMARY KEY)');
        $operation = $semantics->analyze('SELECT v,v+0 FROM (SELECT id IN (SELECT id FROM t) v FROM t LIMIT 1) d', [$table]);
        $type = $operation->field(0)->type;

        self::assertInstanceOf(Known::class, $type);
        self::assertInstanceOf(Domain::class, $type->descriptor);
        self::assertSame($width, $type->descriptor->metadataLength());
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerLimits(): iterable
    {
        yield 'block' => ['SELECT 1 LIMIT 0', true];
        yield 'set' => ['SELECT 1 UNION SELECT 2 LIMIT 1', true];
        yield 'left branch' => ['(SELECT 1 LIMIT 1) UNION SELECT 2', true];
        yield 'right branch' => ['SELECT 1 UNION (SELECT 2 LIMIT 1)', true];
        yield 'values' => ['VALUES ROW(1) LIMIT 1', true];
        yield 'derived' => ['SELECT * FROM (SELECT 1 LIMIT 1) d', false];
        yield 'common table' => ['WITH c AS (SELECT 1 LIMIT 1) SELECT * FROM c', false];
    }

    #[DataProvider('providerLimits')]
    public function testLimitedChecksOnlyTheSubqueryBlocks(string $sql, bool $limited): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze($sql);

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation->statement);
        self::assertSame($limited, (new SubqueryRows())->limited($operation->statement->query));
    }

    #[DataProvider('providerLimits')]
    public function testCheckLimitReportsTheUnsupportedMembershipLimit(string $sql, bool $limited): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1 IN (' . $sql . ')');

        self::assertCount($limited ? 1 : 0, $operation->facts->diagnostics);
        self::assertSame($limited ? "This version of MySQL doesn't yet support 'LIMIT & IN/ALL/ANY/SOME subquery'." : null, ($operation->facts->diagnostics[0] ?? null)?->message());
    }

    public function testCheckLimitRecognizesALegacyLimitAfterALockingClause(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SELECT 1 IN (SELECT 1 FOR UPDATE LIMIT 1)');

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertSame("This version of MySQL doesn't yet support 'LIMIT & IN/ALL/ANY/SOME subquery'.", $operation->facts->diagnostics[0]->message());
    }
}

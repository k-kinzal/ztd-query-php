<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Result;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\Result\OutputFields;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(OutputFields::class)]
#[Small]
final class OutputFieldsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerFields(): iterable
    {
        yield 'grouped aggregate' => ['SELECT MIN(s) FROM t GROUP BY n', 0];
        yield 'direct rollup' => ['SELECT MIN(s) FROM t GROUP BY n WITH ROLLUP', 31];
        yield 'rollup aggregate beside a window' => ['SELECT MIN(s),ROW_NUMBER() OVER () FROM t GROUP BY n WITH ROLLUP', 0];
        yield 'stored rollup' => ['SELECT DISTINCT MIN(s) FROM t GROUP BY n WITH ROLLUP', 0];
        yield 'computed parent' => ['SELECT CONCAT(MIN(s)) FROM t GROUP BY n', 31];
        yield 'distinct computed parent' => ['SELECT DISTINCT CONCAT(MIN(s)) FROM t GROUP BY n', 0];
        yield 'computed window parent' => ['SELECT CONCAT(MIN(MIN(s)) OVER ()) FROM t GROUP BY n', 31];
        yield 'distinct window parent' => ['SELECT DISTINCT CONCAT(MIN(MIN(s)) OVER ()) FROM t GROUP BY n', 0];
        yield 'rollup window parent' => ['SELECT DISTINCT CONCAT(MIN(MIN(s)) OVER ()) FROM t GROUP BY n WITH ROLLUP', 31];
        yield 'aggregate parent beside a window' => ['SELECT DISTINCT CONCAT(MIN(s)),MIN(MIN(s)) OVER () FROM t GROUP BY n WITH ROLLUP', 31];
    }

    #[DataProvider('providerFields')]
    public function testDomainSeparatesStoredFieldsFromComputedAggregateParents(string $sql, int $decimals): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (s VARCHAR(10),n INT)');
        $query = $semantics->analyze($sql, [$table]);
        $type = $query->field(0)->type;

        self::assertInstanceOf(Known::class, $type);
        self::assertInstanceOf(Domain::class, $type->descriptor);
        self::assertSame($decimals, $type->descriptor->decimals);
    }

    public function testStoredKeepsGroupedAggregateFieldsSeparateFromComputedParents(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t(s VARCHAR(10),n INT)');
        $query = $semantics->analyze('SELECT MIN(s),CONCAT(MIN(s)) FROM t GROUP BY n', [$table]);
        $bare = $query->field(0)->type;
        $parent = $query->field(1)->type;
        self::assertInstanceOf(Known::class, $bare);
        self::assertInstanceOf(Domain::class, $bare->descriptor);
        self::assertInstanceOf(Known::class, $parent);
        self::assertInstanceOf(Domain::class, $parent->descriptor);

        self::assertSame([0, 31], [$bare->descriptor->decimals, $parent->descriptor->decimals]);
    }

    public function testStoredJsonStoresAggregateFieldsBeforeARollupWindow(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t(n INT)');
        $query = $semantics->analyze('SELECT JSON_ARRAYAGG(n>1),ROW_NUMBER() OVER () FROM t GROUP BY n WITH ROLLUP', [$table]);
        $type = $query->field(0)->type;

        self::assertInstanceOf(Known::class, $type);
        self::assertInstanceOf(Domain::class, $type->descriptor);
        self::assertSame([4294967295, 0], [$type->descriptor->length, $type->descriptor->decimals]);
    }

    public function testDomainUsesTheSessionConcatenationBoundWhenStoringRollupFields(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t(n INT,s VARCHAR(10))');
        $context = $semantics->context([$table])->withSession(new \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_0900_ai_ci'), groupConcatMaxLen: 4096));
        $query = $semantics->analyze('SELECT GROUP_CONCAT(s),ROW_NUMBER() OVER () FROM t GROUP BY n WITH ROLLUP', $context);
        $type = $query->field(0)->type;

        self::assertInstanceOf(Known::class, $type);
        self::assertInstanceOf(Domain::class, $type->descriptor);
        self::assertSame(['Blob', 16384, 0], [$type->descriptor->field->name, $type->descriptor->length, $type->descriptor->decimals]);
    }

    public function testDeferredExcludesAggregateParentsButKeepsBareCallsAsKeys(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (s VARCHAR(10),n INT)');
        $query = $semantics->analyze('SELECT DISTINCT CONCAT(MIN(s)),MIN(s),CONCAT(MIN(MIN(s)) OVER ()),MIN(MIN(s)) OVER () FROM t GROUP BY n WITH ROLLUP', [$table]);
        $select = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Select::class, $select);
        $fields = new OutputFields();

        self::assertTrue($fields->deferred($select, $query->field(0)->expression));
        self::assertFalse($fields->deferred($select, $query->field(1)->expression));
        self::assertTrue($fields->deferred($select, $query->field(2)->expression));
        self::assertFalse($fields->deferred($select, $query->field(3)->expression));
    }


    public function testCopiedDistinguishesColumnAggregatesFromComputedAndWindowedCalls(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t(n INT,s TEXT)');
        $query = $semantics->analyze('SELECT MIN(s),MAX((s)),MIN(CONCAT(s)),MIN(MIN(s)) OVER () FROM t GROUP BY n', [$table]);
        $fields = new OutputFields();

        self::assertTrue($fields->copied($query->field(0)->expression, $query->facts));
        self::assertTrue($fields->copied($query->field(1)->expression, $query->facts));
        self::assertFalse($fields->copied($query->field(2)->expression, $query->facts));
        self::assertFalse($fields->copied($query->field(3)->expression, $query->facts));
        self::assertFalse($fields->copied($query->field(0)->expression, null));
        self::assertFalse($fields->copied(null, $query->facts));
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function providerCopiedFields(): iterable
    {
        foreach (['VARCHAR(513)' => ['VarString', 513], 'TEXT' => ['Blob', 65535], 'MEDIUMTEXT' => ['Blob', 16777215], 'LONGTEXT' => ['Blob', 4294967295]] as $type => [$field, $length]) {
            foreach (['SELECT MIN(s) FROM t GROUP BY n', 'SELECT * FROM (SELECT MAX(s) AS v FROM t GROUP BY n) AS d'] as $sql) {
                yield $type . ' ' . $sql => ['CREATE TABLE t(n INT,s ' . $type . ') CHARACTER SET utf8mb4; ' . $sql, $field, $length];
            }
        }
    }

    #[DataProvider('providerCopiedFields')]
    public function testDomainKeepsCopiedAggregateTypesThroughDerivedTables(string $sql, string $field, int $length): void
    {
        [$ddl, $select] = explode('; ', $sql, 2);
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze($ddl);
        $query = $semantics->analyze($select, [$table]);
        $type = $query->field(0)->type;
        self::assertInstanceOf(Known::class, $type);
        self::assertInstanceOf(Domain::class, $type->descriptor);

        self::assertSame([$field, $length], [$type->descriptor->field->name, $type->descriptor->length]);
    }

}

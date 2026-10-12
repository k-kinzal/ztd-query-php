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

}

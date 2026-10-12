<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Grouping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\Grouping\OrderingAliases;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Statement\Fact\Diagnostic;

#[CoversClass(OrderingAliases::class)]
#[Medium]
final class OrderingAliasesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function providerCheck(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $version) {
            foreach (['v', '(v)', 'ABS(v)', 'v+1'] as $order) {
                foreach (['', ' GROUP BY a'] as $group) {
                    $expected = str_starts_with($version, '5.') && in_array($order, ['ABS(v)', 'v+1'], true) ? ["Reference 'v' not supported (reference to group function)"] : [];
                    yield $version . $group . $order => [$version, 'SELECT MIN(a) AS v FROM t' . $group . ' ORDER BY ' . $order, $expected];
                }
            }
        }
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerCheck')]
    public function testCheckDistinguishesBareAndEmbeddedAliases(string $version, string $sql, array $expected): void
    {
        $semantics = new Semantics(Dialect::MySql, $version);
        $table = $semantics->analyze('CREATE TABLE t(a INT)');
        $operation = $semantics->analyze($sql, [$table]);

        self::assertSame($expected, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    public function testNestedLeavesAnIndependentSubqueryInItsOwnScope(): void
    {
        $semantics = new Semantics(Dialect::MySql, '5.7.44');
        $table = $semantics->analyze('CREATE TABLE t(a INT)');
        $operation = $semantics->analyze('SELECT MIN(a) AS v FROM t ORDER BY (SELECT MIN(a) FROM t)', [$table]);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $owned = array_fill_keys(array_map(spl_object_id(...), $operation->facts->query($select)->aggregates), true);

        self::assertNull((new OrderingAliases())->nested($select->orderBy[0]->expression, $operation->facts, $owned));
        self::assertSame([], $operation->facts->diagnostics);
    }
}

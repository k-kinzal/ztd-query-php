<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Audit\SessionSetup;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\GroupedRow;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\InList;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Statement\Scalar;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class NestedRowStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<Scalar>}>
     */
    public static function providerStructures(): iterable
    {
        $literal = static fn (?int $value): Scalar => $value === null ? new NullLiteral() : new NumberLiteral((string) $value);
        $row = static fn (?int $one, ?int $two, ?int $three): Row => new Row([new GroupedRow(new Row([$literal($one), $literal($two)], OptionalWords::Written)), $literal($three)]);
        $structures = [];
        foreach (ComparisonOperator::cases() as $operator) {
            foreach ([[1, 2, 3, 1, 2, 3], [1, 2, 3, 2, 1, 2], [2, 1, 2, 1, 2, 3], [1, null, 3, 1, 2, 4], [1, null, 3, 1, 2, 3], [1, null, 3, 1, null, 3], [null, 2, 3, null, 2, 4], [1, 2, null, 1, 2, 3]] as [$a, $b, $c, $d, $e, $f]) {
                $structures[] = new Comparison($operator, $row($a, $b, $c), $row($d, $e, $f));
            }
        }
        $structures[] = new InList($row(1, 2, 3), [$row(2, 1, 2), $row(1, 2, 3)]);
        $structures[] = new InList($row(1, null, 3), [$row(1, 2, 3), $row(2, 1, 2)], true);
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $structures];
        }
    }

    /**
     * @param list<Scalar> $structures Nested comparisons and IN lists with different NULL positions
     */
    #[DataProvider('providerStructures')]
    public function testNestedStructuresMatchNativeWithoutSourceText(string $release, array $structures): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->set_charset('utf8mb4');
                $native->query("SET SESSION sql_mode='', collation_connection='utf8mb4_bin'");
                $session = (new Instance($release))->connect();
                (new SessionSetup())->apply($session, '');
                $session->text = 'unrelated invalid input';
                $observed = array_map(static function (Scalar $node) use ($native, $session): array {
                    $expected = (new Native())->observe($native, (new Reference())->sql($node, $session->semantics()->profile()));
                    $actual = (new Memory())->observe($session, $node);
                    return [serialize($expected), serialize($actual), $expected['error'][0] ?? null];
                }, $structures);

                self::assertSame(array_column($observed, 0), array_column($observed, 1));
                self::assertSame(array_fill(0, count($structures), null), array_column($observed, 2));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}

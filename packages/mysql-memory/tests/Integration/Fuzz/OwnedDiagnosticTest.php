<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Comparison as Audit;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Call\Window\NullTreatment;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\DefaultOfColumn;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\AtTimeZone;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\TimeZone;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\QuantifiedComparison;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Quantifier;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\ValueRow;
use SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery;
use SqlSemantics\Statement\Identifier\Name;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class OwnedDiagnosticTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release];
        }
    }

    /**
     * @return iterable<string, array{string, \SqlSemantics\Statement\Scalar, int|null, list<int>}>
     */
    public static function providerStructures(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            $window = new WindowFunction(WindowFunctionKind::FirstValue, [new QuantifiedComparison(new BooleanLiteral(false), ComparisonOperator::Less, Quantifier::All, new ValuesQuery([new ValueRow([new NumberLiteral('1')])]))], new Name('\\'), NullTreatment::Respect);
            $cast = new Comparison(ComparisonOperator::GreaterOrEqual, new AtTimeZone(new ColumnUse(new Name('operand_value')), TimeZone::ZeroOffset), new DefaultOfColumn(new ColumnUse(new Name('utf8mb4'))));
            yield $release . ':window' => [$release, $window, null, []];
            yield $release . ':conversion' => [$release, $cast, 1049, [3998]];
        }
    }

    /**
     * @param list<int> $conditions
     */
    #[DataProvider('providerStructures')]
    public function testDirectStructuresRetainTheirContextualConditions(string $release, \SqlSemantics\Statement\Scalar $node, ?int $initial, array $conditions): void
    {
        $observation = (new Audit())->compare($node, new LanguageProfile(GrammarRelease::from('mysql-' . $release)));
        self::assertTrue($observation['matches'], serialize($observation));
        $initialError = $observation['native']['error'] ?? [];
        self::assertIsArray($initialError);
        self::assertSame($initial, $initialError[0] ?? null);
        $history = [$observation, ...$observation['transitions']];
        $lastError = $history[count($history) - 1]['native']['error'] ?? [];
        self::assertIsArray($lastError);
        self::assertNull($lastError[0] ?? null);
        $codes = array_column(array_column(array_column($history, 'native'), 'error'), 0);
        self::assertSame([], array_filter($conditions, static fn (int $code): bool => !in_array($code, $codes, true)));
    }

    #[DataProvider('providerReleases')]
    public function testRowsAndConversionsKeepTheirPositionAmongOtherConditions(string $release): void
    {
        $statements = [
            'SELECT FIRST_VALUE(FALSE < ALL (VALUES ROW())) IGNORE NULLS OVER absent',
            'SELECT FIRST_VALUE(FALSE < ALL (VALUES ROW(1), ROW())) OVER absent',
            'SELECT FIRST_VALUE(FALSE < ALL (VALUES ROW())) OVER ()',
            'SELECT FIRST_VALUE(FALSE < ALL (VALUES ROW(1))) RESPECT NULLS OVER absent',
            'SELECT missing, EXISTS(VALUES ROW())',
            'SELECT EXISTS(VALUES ROW()), missing',
            'VALUES ROW(missing), ROW(1,2)',
            'VALUES ROW(1), ROW(missing,2)',
            "SELECT CAST(NULL AT TIME ZONE '1' AS DATETIME), missing",
            "SELECT CAST(TIMESTAMPADD(MINUTE, 1.5, ?) AT TIME ZONE '1' AS DATETIME) >= DEFAULT(utf8mb4)",
            "SELECT CAST(missing AT TIME ZONE '1' AS DATETIME)",
            "SELECT missing, CAST(1 AT TIME ZONE '+00:00' AS DATETIME)",
            "SELECT CAST(1 AT TIME ZONE '+00:00' AS DATETIME), missing",
            "SELECT CAST(? AT TIME ZONE '+00:00' AS DATETIME)",
            'CREATE DATABASE d', 'USE d',
            'CREATE TABLE t(a TIMESTAMP NULL)',
            "SELECT CAST(a AT TIME ZONE '+00:00' AS DATETIME) FROM t",
            "INSERT INTO t VALUES ('2020-01-02 03:04:05')",
            "SELECT CAST(a AT TIME ZONE '+00:00' AS DATETIME) FROM t",
            "SELECT CAST(a AT TIME ZONE '+01:00' AS DATETIME) FROM t",
            "CREATE PROCEDURE p() SELECT CAST(1 AT TIME ZONE '1' AS DATETIME)",
            "CREATE PROCEDURE q() BEGIN IF 0 THEN SELECT CAST(1 AT TIME ZONE '1' AS DATETIME); END IF; END",
            'CALL q()',
        ];
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $native->query("SET time_zone='+00:00'");
                $memory->query("SET time_zone='+00:00'");
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, substr_count($sql, '?'))), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, substr_count($sql, '?'))), $statements);

                self::assertSame($expected, $actual, $release);
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
    #[DataProvider('providerReleases')]
    public function testSameConversionRespondsToColumnTypeChanges(string $release): void
    {
        $conversion = new AtTimeZone(new ColumnUse(new Name('a')), TimeZone::ZeroOffset);
        $node = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression($conversion)], new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('t')))));
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('CREATE DATABASE d');
                $native->select_db('d');
                $session = (new \MySqlMemory\Instance($release, [], ['d']))->connect('root', 'localhost', 'd');
                $sql = (new \Fuzz\Structure\Reference())->sql($node, $session->semantics()->profile());
                $fixtures = [[], ['CREATE TABLE t(a INT)'], ['ALTER TABLE t MODIFY a TIMESTAMP NULL'], ["INSERT INTO t VALUES ('2020-01-02 03:04:05')"], ['ALTER TABLE t MODIFY a DATETIME']];
                $observed = array_map(static function (array $fixture) use ($native, $session, $node, $sql): array {
                    foreach ($fixture as $text) {
                        $native->query($text);
                        $session->query($text);
                    }
                    $expected = (new Native())->observe($native, $sql);
                    $session->text = 'unrelated invalid input';
                    $actual = (new \Fuzz\Structure\Audit\Memory())->observe($session, $node);

                    return [serialize($expected), serialize($actual), $expected['error'][0] ?? null];
                }, $fixtures);

                self::assertSame(array_column($observed, 0), array_column($observed, 1));
                self::assertSame([1146, 3998, null, null, 3998], array_column($observed, 2));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }

}

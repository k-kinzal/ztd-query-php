<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Choices;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class IdentifierDiagnosticHistoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<M\Utility\Explain\Explain>}>
     */
    public static function providerDatabases(): iterable
    {
        $queries = [];
        for ($trial = 0; $trial < 12; $trial++) {
            $choices = new Choices('explain-database:' . $trial);
            $name = str_repeat('a', 44) . implode('', array_map(static fn (int $i): string => $choices->one(['b', '猫', 'é']), range(1, 20)));
            $queries[] = new M\Utility\Explain\Explain(new M\QueryRoot(new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))])), database: new Name($name));
        }
        foreach (['8.2.0', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $queries];
        }
    }

    /**
     * @param list<M\Utility\Explain\Explain> $queries Directly constructed explanations at the identifier character limit
     */
    #[DataProvider('providerDatabases')]
    public function testTheSameDatabaseOperandResolvesAcrossCatalogChangesWithoutInputSql(string $release, array $queries): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $native->set_charset('utf8mb4');
            $session = (new Instance($release))->connect();
            $session->query('SET NAMES utf8mb4');
            array_map(static function (M\Utility\Explain\Explain $query) use ($native, $session): void {
                self::assertNotNull($query->database);
                $name = $query->database->value;
                $history = array_map(static function (string $setup) use ($native, $session, $query): array {
                    $native->query($setup);
                    $session->query($setup);
                    $expected = (new Native())->observe($native, (new Reference())->sql($query, $session->semantics()->profile()));
                    $session->text = 'opaque non-SQL label';
                    self::assertSame(serialize($expected), serialize((new Memory())->observe($session, $query)), $setup);
                    return $expected;
                }, ['DROP DATABASE IF EXISTS `' . $name . '`', 'CREATE DATABASE `' . $name . '`', 'DROP DATABASE `' . $name . '`', 'CREATE DATABASE `' . $name . '`']);
                self::assertSame([1049, null, 1049, null], array_map(static fn (array $observation): ?int => $observation['error'][0] ?? null, $history));
                self::assertSame($name, $query->database->value);
                self::assertSame(64, mb_strlen($query->database->value, 'UTF-8'));
                self::assertSame('opaque non-SQL label', $session->text);
            }, $queries);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}

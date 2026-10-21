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
final class GeohashHistoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<M\QueryRoot>, list<string>}>
     */
    public static function providerHistories(): iterable
    {
        $history = ['SET @h=NULL, @x=NULL, @y=NULL, @n=1, @r=0'];
        for ($seed = 0; $seed < 32; $seed++) {
            $choices = new Choices('geohashes:' . $seed);
            $history[] = "SET @h='" . $choices->one(['s', 'ezs42', 'u4pruydqqvj', 'ZZZZ', 'a', '']) . "', @x=" . $choices->integer(-181, 181) . ', @y=' . $choices->integer(-91, 91) . ', @n=' . $choices->integer(0, 101) . ', @r=' . $choices->one([0, 4326, 4807, 999999, -1]);
        }
        $history[] = 'SET @h=1, @n=1.0, @r=1.0';
        $history[] = 'SET @h=CAST(NULL AS CHAR), @x=NULL, @y=NULL, @n=1, @r=0';
        foreach (['5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $queries = [];
            foreach (['ST_LATFROMGEOHASH' => ['h'], 'ST_LONGFROMGEOHASH' => ['h'], 'ST_POINTFROMGEOHASH' => ['h', 'r'], 'ST_GEOHASH' => ['x', 'y', 'n']] as $name => $variables) {
                $arguments = array_map(static fn (string $variable): M\Call\CallArgument => new M\Call\CallArgument(new M\Variable\UserVariable(new Name($variable))), $variables);
                $call = new M\Call\FunctionCall(new Name($name), $arguments);
                $expression = $name === 'ST_POINTFROMGEOHASH' ? new M\Call\FunctionCall(new Name('HEX'), [new M\Call\CallArgument($call)]) : $call;
                $queries[] = new M\QueryRoot(new M\Query\Select([], [new M\Query\SelectExpression($expression, new Name('v'))]));
            }
            yield $release => [$release, $queries, $history];
        }
    }

    /**
     * @param list<M\QueryRoot> $queries Geohash calls constructed without SQL
     * @param list<string> $history Generated coordinates, text, lengths and reference identifiers
     */
    #[DataProvider('providerHistories')]
    public function testTheSameSemanticQueriesConsumeChangingGeohashOperandsWithoutInputSql(string $release, array $queries, array $history): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $native->set_charset('utf8mb4');
            $session = (new Instance($release))->connect();
            $session->query('SET NAMES utf8mb4');
            $observations = array_map(static function (string $setup) use ($native, $session, $queries): array {
                $setupNode = $session->analyze($setup)->statement;
                self::assertInstanceOf(M\Statement::class, $setupNode);
                self::assertSame(serialize((new Native())->observe($native, $setup)), serialize((new Memory())->observe($session, $setupNode)), $setup);
                return array_map(static function (M\QueryRoot $query) use ($native, $session, $setup): array {
                    $sql = (new Reference())->sql($query, $session->semantics()->profile());
                    $expected = (new Native())->observe($native, $sql);
                    $session->text = 'opaque non-SQL label';
                    self::assertSame(serialize($expected), serialize((new Memory())->observe($session, $query)), $setup . ': ' . $sql);
                    return $expected;
                }, $queries);
            }, $history);
            self::assertSame([[null]], $observations[0][0]['results'][0]['rows']);
            self::assertSame([[null]], $observations[34][0]['results'][0]['rows']);
            self::assertSame('opaque non-SQL label', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}

<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Comparison;
use Fuzz\Structure\Audit\Fixture;
use Fuzz\Structure\Audit\SessionSetup;
use Fuzz\Structure\Choices;
use Fuzz\Target\DisposableMySql;
use mysqli;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class CommonDependencyInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, int, M\View\AlterView}>
     */
    public static function providerHistories(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 4; $seed++) {
                $choices = new Choices('common-dependency:' . $seed);
                $table = 't' . $choices->integer(0, 100000);
                $value = $choices->integer(-10000, 10000);
                $a = new M\Query\With\CommonTableExpression(new Name('a'), [], new M\Query\ExplicitTable(new QualifiedName(new Name($table))));
                $b = new M\Query\With\CommonTableExpression(new Name('b'), [], new M\Query\ExplicitTable(new QualifiedName(new Name('a'))));
                $query = new M\Query\QueryExpression(new M\Query\With\With(false, [$a, $b]), new M\Query\ExplicitTable(new QualifiedName(new Name('a'))));
                $original = new M\View\AlterView(new M\View\ViewDefinition(new QualifiedName(new Name('v')), $query));
                yield $release . ':' . $seed => [$release, $table, $value, $original];
            }
        }
    }

    #[DataProvider('providerHistories')]
    public function testUnchangedViewResolvesTheReachedCommonDependency(string $release, string $table, int $value, M\View\AlterView $original): void
    {
        $definition = new DisposableMySql($release);
        $container = Testcontainers::run($definition);
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->set_charset('utf8mb4');
                $native->query("SET SESSION sql_mode='', collation_connection='utf8mb4_bin', timestamp=1700000000");
                $session = (new Fixture())->session($endpoint, $native, $release, $definition->supervised());
                (new SessionSetup())->apply($session, '');
                $session->text = 'unrelated invalid input';
                $profile = $session->semantics()->profile();
                $observer = new Comparison();
                $database = new M\Server\Database\CreateDatabase(false, new Name('d'));
                $select = new M\Utility\Explain\UseDatabase(new Name('d'));
                $setup = array_map(static fn ($node): bool => $observer->observe($node, $profile, $native, $session, $endpoint)['matches'], [$database, $select]);
                $before = $observer->observe($original, $profile, $native, $session, $endpoint);
                $native->query('CREATE TABLE ' . $table . '(x INT)');
                $session->query('CREATE TABLE ' . $table . '(x INT)');
                $native->query('INSERT INTO ' . $table . ' VALUES (' . $value . ')');
                $session->query('INSERT INTO ' . $table . ' VALUES (' . $value . ')');
                $session->text = 'unrelated invalid input';
                $target = $observer->observe($original, $profile, $native, $session, $endpoint);
                $created = $observer->observe(new M\View\CreateView(new M\View\ViewDefinition(new QualifiedName(new Name('v')), new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'), new Name('x'))]))), $profile, $native, $session, $endpoint);
                $after = $observer->observe($original, $profile, $native, $session, $endpoint);
                $stored = $observer->observe(new M\QueryRoot(new M\Query\ExplicitTable(new QualifiedName(new Name('v')))), $profile, $native, $session, $endpoint);
                $rows = $observer->observe(new M\QueryRoot($original->definition->query), $profile, $native, $session, $endpoint);
                self::assertSame([true, true, true, true, true, true, true, true], [...$setup, ...array_column([$before, $target, $created, $after, $stored, $rows], 'matches')]);
                self::assertSame([1146, '42S02', "Table 'd.{$table}' doesn't exist"], $before['native']['error']);
                self::assertSame([1146, '42S02', "Table 'd.v' doesn't exist"], $target['native']['error']);
                self::assertArrayNotHasKey('error', $after['native']);
                $error = $stored['native']['error'];
                self::assertIsArray($error);
                self::assertSame(1064, $error[0]);
                $results = $rows['native']['results'];
                self::assertIsArray($results);
                self::assertIsArray($results[0]);
                self::assertSame([[(string) $value]], $results[0]['rows']);
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}

<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use Fuzz\Target\Observer;
use mysqli;
use MySqlMemory\Instance;
use MySqlMemory\Server\Server;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Json\NestedColumns;
use SqlSemantics\Platform\MySql\Statement\Call\Json\OrdinalityColumn;
use SqlSemantics\Platform\MySql\Statement\Call\Json\PathColumn;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Star;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Relation\JsonTable;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;
use SqlSemantics\Statement\Identifier\Name;
use Testcontainers\Containers\PortStrategy\StaticPortStrategy;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class JsonTableAliasTest extends TestCase
{
    /**
     * @return iterable<string, array{string, DisposableMySql, list<QueryRoot>, list<string>}>
     */
    public static function providerStructures(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            $definition = new DisposableMySql($release);
            $port = filter_var(getenv('MYSQL_MEMORY_ALIAS_PORT'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
            if (is_int($port)) {
                $definition->withPortStrategy(new StaticPortStrategy($port));
            }
            $nodes = array_map(static fn (string $alias): QueryRoot => new QueryRoot(new Select([], [new Star()], new JsonTable(new StringLiteral(['[1,2,3]']), new StringLiteral(['$[*]']), [new OrdinalityColumn(new Name('n'))], new Name($alias)))), ['j', '0', 'two words', 'j`n', '猫']);
            $nodes[] = new QueryRoot(new Select([], [new SelectExpression(new Arithmetic(ArithmeticOperator::Minus, new ColumnUse(new Name('n')), new NumberLiteral('2')))], new JsonTable(new StringLiteral(['[1,2,3]']), new StringLiteral(['$[*]']), [new OrdinalityColumn(new Name('n'))], new Name('j'))));
            $nodes[] = new QueryRoot(new Select([], [new Star()], new JsonTable(new StringLiteral(['[{"a":[2,3]},{}]']), new StringLiteral(['$[*]']), [new OrdinalityColumn(new Name('n')), new NestedColumns(new StringLiteral(['$.a[*]']), [new OrdinalityColumn(new Name('m')), new PathColumn(new Name('v'), new Integral(IntegralKind::Int, modifiers: [NumericModifier::Unsigned]), new StringLiteral(['$']))])], new Name('j'))));
            $sql = [];
            foreach (['', ' AS ``', ' AS j'] as $alias) {
                $table = "JSON_TABLE('[1]', '$[*]' COLUMNS(n FOR ORDINALITY))" . $alias;
                $sql[] = 'SELECT * FROM ' . $table;
                $sql[] = 'SELECT * FROM missing, ' . $table;
                $sql[] = 'SELECT * FROM ' . $table . ' WHERE';
                $sql[] = 'SELECT * FROM ' . $table . ', JSON_TABLE(BINARY 1, \'$\' COLUMNS(n FOR ORDINALITY)) q';
                $sql[] = 'CREATE PROCEDURE p' . count($sql) . '() BEGIN IF 0 THEN SELECT * FROM ' . $table . '; END IF; END';
            }
            yield $release => [$release, $definition, $nodes, $sql];
        }
    }

    /**
     * @param list<QueryRoot> $nodes Original constructed queries with nonempty aliases
     * @param list<string> $sql Invalid input, competing errors and deferred routine bodies
     */
    #[DataProvider('providerStructures')]
    public function testAliasesRetainRowsAndRefusalsBeforePublication(string $release, DisposableMySql $definition, array $nodes, array $sql): void
    {
        $container = Testcontainers::run($definition);
        $server = null;
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->set_charset('utf8mb4');
                $session = (new Instance($release))->connect();
                $session->run('SET NAMES utf8mb4');
                $session->text = 'unrelated invalid input';
                $profile = $session->semantics()->profile();
                $expected = array_map(static fn (QueryRoot $node): string => serialize((new Native())->observe($native, (new Reference())->sql($node, $profile))), $nodes);
                $actual = array_map(static fn (QueryRoot $node): string => serialize((new Memory())->observe($session, $node)), $nodes);
                self::assertSame($expected, $actual);
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
            $server = Server::start($release);
            $connections = [new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port, $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]), new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION])];
            $observations = array_map(static function (PDO $connection) use ($sql): array {
                $connection->exec('SET NAMES utf8mb4');
                return array_map(static fn (string $input): string => serialize((new Observer())->observe($connection, $input, true)), $sql);
            }, $connections);
            self::assertSame($observations[0], $observations[1]);
        } finally {
            $server?->stop();
            $container->stop();
        }
    }
}

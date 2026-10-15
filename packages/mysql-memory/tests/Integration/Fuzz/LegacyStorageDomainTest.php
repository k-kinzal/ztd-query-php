<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use mysqli;
use mysqli_result;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterLogfileGroup;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespaceAccess;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespaceDatafile;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateLogfileGroup;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateTablespace;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\DropLogfileGroup;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\DropTablespace;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\LogFile;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\DatafileAction;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\EngineOption;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\LogFileKind;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\TablespaceAccess;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Identifier\Name;
use Testcontainers\Containers\PortStrategy\StaticPortStrategy;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class LegacyStorageDomainTest extends TestCase
{
    /**
     * @return iterable<string, array{string, DisposableMySql, list<Statement>}>
     */
    public static function providerStructures(): iterable
    {
        foreach (['5.6.51'] as $release) {
            $definition = new DisposableMySql($release);
            $port = filter_var(getenv('MYSQL_MEMORY_STORAGE_PORT'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
            if (is_int($port)) {
                $definition->withPortStrategy(new StaticPortStrategy($port));
            }
            $nodes = [];
            foreach ([null, 'InnoDB', 'MyISAM', 'MEMORY', 'unknown_engine', 'NDB'] as $engine) {
                $options = $engine === null ? [] : [new EngineOption(new Name($engine))];
                $nodes[] = new CreateTablespace(new Name('s'), new Text('text'), options: $options);
                $nodes[] = new DropTablespace(new Name('s'), $options);
                $nodes[] = new DropLogfileGroup(new Name('g'), $options);
                foreach ([DatafileAction::Add, DatafileAction::Drop] as $action) {
                    $nodes[] = new AlterTablespaceDatafile(new Name('s'), $action, new Text('text'), $options);
                }
                foreach (LogFileKind::cases() as $kind) {
                    $nodes[] = new CreateLogfileGroup(new Name('g'), new LogFile($kind, new Text('text')), $options);
                    $nodes[] = new AlterLogfileGroup(new Name('g'), new LogFile($kind, new Text('text')), $options);
                }
            }
            foreach (TablespaceAccess::cases() as $access) {
                $nodes[] = new AlterTablespaceAccess(new Name('s'), $access);
            }
            yield $release => [$release, $definition, $nodes];
        }
    }

    /**
     * @param list<Statement> $nodes The same storage requests across session engine and substitution settings
     */
    #[DataProvider('providerStructures')]
    public function testSameStorageRequestsResolveSessionEngineState(string $release, DisposableMySql $definition, array $nodes): void
    {
        $container = Testcontainers::run($definition);
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $session = (new Instance($release))->connect();
                $fixture = ['SET NAMES utf8mb4 COLLATE utf8mb4_general_ci'];
                array_map(static fn (string $sql): bool|mysqli_result => $native->query($sql), $fixture);
                array_map($session->run(...), $fixture);
                $stages = [['SET default_storage_engine=InnoDB', "SET sql_mode='NO_ENGINE_SUBSTITUTION'"], ['SET default_storage_engine=InnoDB', "SET sql_mode=''"], ['SET default_storage_engine=MyISAM', "SET sql_mode='NO_ENGINE_SUBSTITUTION'"], ['SET default_storage_engine=MyISAM', "SET sql_mode=''"]];
                $results = array_map(static function (array $setup) use ($native, $session, $nodes): array {
                    array_map(static fn (string $sql): bool|mysqli_result => $native->query($sql), $setup);
                    array_map($session->run(...), $setup);
                    $profile = $session->semantics()->profile();
                    $expected = array_map(static fn (Statement $node): string => serialize((new Native())->observe($native, (new Reference())->sql($node, $profile))), $nodes);
                    $session->text = 'unrelated invalid input';
                    $actual = array_map(static fn (Statement $node): string => serialize((new Memory())->observe($session, $node)), $nodes);

                    return [$expected, $actual];
                }, $stages);

                self::assertSame(array_column($results, 0), array_column($results, 1));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}

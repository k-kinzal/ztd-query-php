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
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowDatabases;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTables;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowLike;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowListing;
use SqlSemantics\Statement\Identifier\Name;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class ShowHeadingStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<ShowDatabases|ShowTables>}>
     */
    public static function providerStructures(): iterable
    {
        $choices = new Choices('inspection-headings-20261018');
        $patterns = ['t', "x\0" . str_repeat('a', 300), '😀'];
        foreach (['a', 'é', 'あ'] as $character) {
            foreach ([242, 245, 256, 511, 600] as $bytes) {
                $patterns[] = str_repeat($character, intdiv($bytes + $choices->integer(-1, 1), strlen($character)));
            }
        }
        $structures = [];
        foreach ($patterns as $pattern) {
            $filter = new ShowLike(new Text($pattern));
            $structures[] = new ShowDatabases($filter);
            $structures[] = new ShowTables(filter: $filter);
            $structures[] = new ShowTables(ShowListing::Full, new Name('d'), $filter);
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $structures];
        }
    }

    /**
     * @param list<ShowDatabases|ShowTables> $structures Original typed inspection values
     */
    #[DataProvider('providerStructures')]
    public function testTheSameInspectionResolvesNamesAgainstEachDatabaseState(string $release, array $structures): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $session = (new Instance($release))->connect();
            array_map(static function (string $step) use ($native, $session): void {
                $native->query($step);
                $session->query($step);
            }, ['SET NAMES utf8mb4', 'CREATE DATABASE d', 'CREATE DATABASE another_database', 'CREATE TABLE d.t(a INT)']);
            $histories = array_map(static function (string $step) use ($native, $session, $structures): array {
                $native->query($step);
                $session->query($step);
                return array_map(static function (ShowDatabases|ShowTables $structure) use ($native, $session): array {
                    $session->text = 'unrelated invalid input';
                    $sql = (new Reference())->sql($structure, $session->semantics()->profile());
                    return [(new Native())->observe($native, $sql), (new Memory())->observe($session, $structure)];
                }, $structures);
            }, ['USE d', 'USE another_database', 'DROP DATABASE another_database']);
            $observations = array_merge(...$histories);
            self::assertSame(array_map(serialize(...), array_column($observations, 0)), array_map(serialize(...), array_column($observations, 1)));
            self::assertSame('unrelated invalid input', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}

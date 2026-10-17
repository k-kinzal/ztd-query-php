<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\DropTable;
use SqlSemantics\Platform\MySql\Statement\Alter\TargetTable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class StructureStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerStates(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release . ': table' => [$release, false];
            yield $release . ': temporary' => [$release, true];
        }
    }

    #[DataProvider('providerStates')]
    public function testSameStructureRequiresADatabaseEvenWithIfExists(string $release, bool $temporary): void
    {
        $node = new DropTable($temporary, true, [new TargetTable(new QualifiedName(new Name('t')))]);
        $sql = 'DROP ' . ($temporary ? 'TEMPORARY ' : '') . 'TABLE IF EXISTS t';
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $session = (new Instance($release))->connect();
                $session->text = 'unrelated invalid input';
                $first = (new Memory())->observe($session, $node);
                self::assertSame((new Native())->observe($native, $sql), $first);
                self::assertSame([1046, '3D000', 'No database selected'], $first['error'] ?? null);
                $native->query('CREATE DATABASE d');
                $native->query('USE d');
                $session->query('CREATE DATABASE d; USE d');
                $session->text = 'unrelated invalid input';
                $second = (new Memory())->observe($session, $node);
                self::assertSame((new Native())->observe($native, $sql), $second);
                self::assertArrayNotHasKey('error', $second);
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}

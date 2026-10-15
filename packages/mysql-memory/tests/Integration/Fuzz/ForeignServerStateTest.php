<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\AlterServer;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\CreateServer;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\DropServer;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\ServerOption;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\ServerOptionKind;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Identifier\Name;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class ForeignServerStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<list<Statement>>, list<list<int|null>>}>
     */
    public static function providerHistories(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $histories = [];
            $expected = [];
            foreach (['ABCDE', 'a b', 'Admin_É', str_repeat('a', 61) . '猫猫', str_repeat('b', 300), str_repeat('猫', 150)] as $name) {
                $create = new CreateServer(new Name($name), new Name('mysql'), [new ServerOption(ServerOptionKind::Host, new Text('localhost'))]);
                $alter = new AlterServer(new Name($name), [new ServerOption(ServerOptionKind::Host, new Text('example.test'))]);
                $drop = new DropServer(false, new Name($name));
                $optional = new DropServer(true, new Name($name));
                $histories[] = [$optional, $drop, $create, $create, $alter, $drop, $drop, $optional, $create, $optional];
                $long = mb_strlen($name) > 64;
                $expected[] = [null, 1477, null, 1476, null, $long ? 1477 : null, 1477, null, $long && $release === '5.6.51' ? 1476 : null, null];
            }
            yield $release => [$release, $histories, $expected];
        }
    }

    /**
     * @param list<list<Statement>> $histories The same objects repeated across server creation and removal
     * @param list<list<int|null>> $errors The native transition outcomes, including the legacy conditional-drop behavior
     */
    #[DataProvider('providerHistories')]
    public function testSameStructuresTrackServerExistenceAndDiagnosticHistory(string $release, array $histories, array $errors): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $session = (new Instance($release))->connect();
                $native->query('SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $session->run('SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $profile = $session->semantics()->profile();
                $results = array_map(static function (array $history) use ($native, $session, $profile): array {
                    return array_map(static function (Statement $node) use ($native, $session, $profile): array {
                        $expected = (new Native())->observe($native, (new Reference())->sql($node, $profile));
                        $session->text = 'unrelated invalid input';
                        $actual = (new Memory())->observe($session, $node);
                        return [serialize($expected), serialize($actual), $expected['error'][0] ?? null];
                    }, $history);
                }, $histories);

                self::assertSame(array_map(static fn (array $history): array => array_column($history, 0), $results), array_map(static fn (array $history): array => array_column($history, 1), $results));
                self::assertSame($errors, array_map(static fn (array $history): array => array_column($history, 2), $results));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}

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
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class WarningObservationStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release];
        }
    }

    #[DataProvider('providerReleases')]
    public function testPasswordExpirationRefusesConditionsUntilTheSameSessionResetsIt(string $release): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->set_charset('utf8mb4');
                $session = (new Instance($release))->connect();
                $session->query('SET NAMES utf8mb4');
                $expiry = new \SqlSemantics\Platform\MySql\Statement\Account\AlterUser(false, [new \SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification(new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser())], options: [new \SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOption(\SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOptionKind::ExpireNow)]);
                $reset = new \SqlSemantics\Platform\MySql\Statement\Account\AlterUser(false, [new \SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification(new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser(), new \SqlSemantics\Platform\MySql\Statement\Account\User\Identification(null, \SqlSemantics\Platform\MySql\Statement\Account\User\Credential::Password, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('replacement')))]);
                $warning = new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowWarnings();
                $observed = array_map(static function (\SqlSemantics\Platform\MySql\Statement\Statement $node) use ($native, $session): array {
                    $sql = (new Reference())->sql($node, $session->semantics()->profile());
                    $expected = (new Native())->observe($native, $sql);
                    $session->text = 'unrelated invalid input';
                    $actual = (new Memory())->observe($session, $node);

                    return [serialize($expected), serialize($actual), $actual['warningsError'][0] ?? null];
                }, [$warning, $expiry, $warning, $reset, $warning]);

                self::assertSame(array_column($observed, 0), array_column($observed, 1));
                self::assertSame([null, 1820, 1820, null, null], array_column($observed, 2));
                self::assertSame('unrelated invalid input', $session->text);

            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}

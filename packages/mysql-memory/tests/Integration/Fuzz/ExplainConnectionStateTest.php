<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Audit\SessionSetup;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use Fuzz\Target\Process\Disconnection;
use mysqli;
use MySqlMemory\Instance;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection;
use SqlSemantics\Statement\Identifier\Name;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class ExplainConnectionStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<Name|null>}>
     */
    public static function providerHistories(): iterable
    {
        foreach (['5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, str_starts_with($release, '5.') ? [null, new Name('JSON'), new Name('TRADITIONAL')] : [null, new Name('JSON'), new Name('TRADITIONAL'), new Name('TREE')]];
        }
    }

    /**
     * @param list<Name|null> $formats Every explicit format available in this release and the session default
     */
    #[DataProvider('providerHistories')]
    public function testSameRequestsFollowTheirTargetConnectionState(string $release, array $formats): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $target = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $guard = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port, $endpoint->username, $endpoint->password);
            try {
                $native->set_charset('utf8mb4');
                $native->query("SET sql_mode='', collation_connection='utf8mb4_bin'");
                $instance = new Instance($release);
                $session = $instance->connect(threadId: $native->thread_id);
                $memoryTarget = $instance->connect(threadId: $target->thread_id);
                (new SessionSetup())->apply($session, '');
                $session->text = 'unrelated invalid input';
                $requests = array_map(static fn (?Name $format): ExplainConnection => new ExplainConnection(new Numeral((string) $target->thread_id), $format), $formats);
                $observe = static function (ExplainConnection $request) use ($native, $session): array {
                    $expected = (new Native())->observe($native, (new Reference())->sql($request, $session->semantics()->profile()));
                    return [serialize($expected), serialize((new Memory())->observe($session, $request)), $expected['error'][0] ?? null];
                };
                $alive = array_map($observe, $requests);
                $targetId = $target->thread_id;
                $target->close();
                $memoryTarget->close();
                self::assertTrue((new Disconnection())->wait($guard, $targetId, true));
                $absent = array_map($observe, $requests);

                self::assertSame(array_column($alive, 0), array_column($alive, 1));
                self::assertSame(array_fill(0, count($requests), null), array_column($alive, 2));
                self::assertSame(array_column($absent, 0), array_column($absent, 1));
                self::assertSame(array_fill(0, count($requests), 1094), array_column($absent, 2));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}

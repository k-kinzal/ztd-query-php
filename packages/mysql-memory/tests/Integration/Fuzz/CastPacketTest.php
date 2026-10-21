<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\PacketLimit;
use Fuzz\Target\DisposableMySql;
use mysqli;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Statement\Scalar;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class CastPacketTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<Scalar>}>
     */
    public static function providerInputs(): iterable
    {
        $nodes = [new \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast(new StringLiteral(['abc']), new \SqlSemantics\Platform\MySql\Statement\Type\CastTarget(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Binary, '4097'))];
        foreach ([\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Binary, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Char, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::NationalChar] as $kind) {
            foreach (['1024', '4096', '4097', '65536', '65537', '2147483648', '4294967295'] as $length) {
                $target = new \SqlSemantics\Platform\MySql\Statement\Type\CastTarget($kind, $length);
                $nodes[] = new \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast(new StringLiteral(['abc']), $target);
                $nodes[] = new \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast(new NullLiteral(), $target);
            }
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $nodes];
        }
    }

    /**
     * @param list<Scalar> $nodes
     */
    #[DataProvider('providerInputs')]
    public function testSameStructuresObserveThePacketLimitTransition(string $release, array $nodes): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $admin = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $admin->query('SET GLOBAL max_allowed_packet=4096');
                $first = array_map(fn (Scalar $node): array => (new PacketLimit())->compare($endpoint, $release, $node, 4096), $nodes);
                $admin->query('SET GLOBAL max_allowed_packet=65536');
                $second = array_map(fn (Scalar $node): array => (new PacketLimit())->compare($endpoint, $release, $node, 65536), $nodes);

                $observations = [...$first, ...$second];
                self::assertSame(array_column($observations, 'native'), array_column($observations, 'memory'), $release);
                self::assertSame(array_fill(0, count($observations), 'unrelated invalid input'), array_column($observations, 'sourceText'));
                self::assertSame(serialize([[null]]), $first[0]['rows']);
                self::assertSame(serialize([[str_pad('abc', 4097, "\0")]]), $second[0]['rows']);
            } finally {
                $admin->close();
            }
        } finally {
            $container->stop();
        }
    }

}

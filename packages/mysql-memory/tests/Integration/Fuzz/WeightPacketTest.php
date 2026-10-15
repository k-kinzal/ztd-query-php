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
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightCast;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightString;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightStringParameters;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Statement\Scalar;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class WeightPacketTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<Scalar>}>
     */
    public static function providerInputs(): iterable
    {
        $nodes = [new WeightString(new StringLiteral(['abc']), WeightCast::Binary, new Numeral('4097'))];
        foreach (WeightCast::cases() as $cast) {
            foreach (['1365', '1366', '4096', '4097', '65536', '1073741824', '2147483648', '2147483649', '4294967295', '4294967296', '4294967297'] as $length) {
                $nodes[] = new WeightString(new StringLiteral(['abc']), $cast, new Numeral($length));
                $nodes[] = new WeightString(new NullLiteral(), $cast, new Numeral($length));
            }
        }
        $nodes[] = new WeightStringParameters(new StringLiteral(['abc']), new Numeral('1'), new Numeral('4294967295'), new Numeral('128'));
        foreach ([[new Numeral('A', true), new Numeral('2'), new Numeral('0')], [new Numeral('0'), new Numeral('A', true), new Numeral('80', true)], [new Numeral('1e2'), new Numeral('2.9'), new Numeral('0')], [new Numeral('.5'), new Numeral('1'), new Numeral('0')], [new Numeral('18446744073709551616'), new Numeral('1'), new Numeral('0')]] as [$length, $count, $flags]) {
            $nodes[] = new WeightStringParameters(new StringLiteral(['abc']), $length, $count, $flags);
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

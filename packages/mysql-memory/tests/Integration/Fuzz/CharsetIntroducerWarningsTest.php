<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class CharsetIntroducerWarningsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     * @throws RuntimeException When the retained fuzz input cannot be read
     */
    public static function providerReleases(): iterable
    {
        $retained = file_get_contents(__DIR__ . '/fixtures/charset-introducer-before-marker.sql');
        if ($retained === false) {
            throw new RuntimeException('The retained fuzz input is missing.');
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $statements = [];
            foreach (['utf8', 'utf8mb3', 'ucs2', 'utf8mb4', 'utf16', 'utf16le', 'utf32', 'binary'] as $charset) {
                foreach (["'a'", "X'0061'", "B'01100001'", '', '+', "X'0061', !", "'a' 'b', !"] as $literal) {
                    $statements[] = 'SELECT _' . $charset . ' ' . $literal;
                }
                $statements[] = 'SHOW _' . $charset;
                $statements[] = 'SELECT 1 _' . $charset . " X'00'";
            }
            foreach (['ucs2', 'utf16', 'utf16le', 'utf32', 'binary'] as $charset) {
                foreach (['', '61', '0061', '616263', '00000061'] as $bytes) {
                    $bits = implode('', array_map(static fn (string $digit): string => str_pad(base_convert($digit, 16, 2), 4, '0', STR_PAD_LEFT), str_split($bytes)));
                    foreach (["X'" . $bytes . "'", "B'" . $bits . "'"] as $literal) {
                        $value = '_' . $charset . ' ' . $literal;
                        $statements[] = 'SELECT HEX(' . $value . '), LENGTH(' . $value . '), CHAR_LENGTH(' . $value . ')';
                    }
                }
            }
            foreach (["X'00110000'", "X'0000D800'", "X'0000006100616263'"] as $literal) {
                $statements[] = 'SELECT _utf32 ' . $literal . ' FROM missing';
                $statements[] = 'SELECT _utf32 ' . $literal . ', ?';
                $statements[] = 'WITH c AS (SELECT _utf32 ' . $literal . ') SELECT 1';
            }
            $statements[] = $retained;
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Preparation conditions, result metadata and ordered session effects
     */
    #[DataProvider('providerReleases')]
    public function testIntroducerWarningsSurviveLaterInputRefusals(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, 0)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, 0)), $statements);

                self::assertSame($expected, $actual, $release);
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}

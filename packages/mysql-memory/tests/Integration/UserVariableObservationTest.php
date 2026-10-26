<?php

declare(strict_types=1);

namespace Tests\Integration;

use Container\Endpoint;
use Fuzz\Container\DisposableMySql;
use Fuzz\Observation\UserVariables;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class UserVariableObservationTest extends TestCase
{
    #[DataProvider('providerVersions')]
    public function testTheInventoryRetainsValuesTypesAndOwnership(string $version): void
    {
        $server = Testcontainers::run(new DisposableMySql($version));
        try {
            $endpoint = $server->getData(Endpoint::class);
            $owner = new PDO($endpoint->dsn(), $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $administrator = new PDO($endpoint->dsn(), $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $observer = new UserVariables();
            $initial = $observer->capture($owner, $administrator);
            self::assertSame([], $initial['raw']['inventory']);
            $administrator->exec('SET @foreign_variable=99');
            $owner->exec("SET @integer_value=1, @text_value='1', @null_value=NULL, @bytes=REPEAT(0x00FF,8192), @`quo``ted`='ok', @`日本語`='name'");
            $owner->exec("SET SESSION character_set_client='latin1'");
            $owner->exec('BEGIN');
            $owner->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $captured = $observer->capture($owner, $administrator);
            self::assertTrue($owner->inTransaction());
            self::assertFalse($owner->getAttribute(PDO::ATTR_EMULATE_PREPARES));
            $owner->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
            $client = $owner->query('SELECT @@character_set_client');
            self::assertNotFalse($client);
            self::assertSame('latin1', $client->fetchColumn());
            self::assertIsArray($captured['raw']['inventory']);
            $inventory = array_column($captured['raw']['inventory'], 1, 0);
            self::assertCount(6, $inventory);
            self::assertArrayNotHasKey('foreign_variable', $inventory);
            self::assertSame(str_repeat("\x00\xFF", 8192), $inventory['bytes']);
            self::assertNull($inventory['null_value']);
            self::assertSame('ok', $inventory['quo`ted']);
            self::assertSame('name', $inventory['日本語']);
            self::assertIsArray($captured['comparable']['values']);
            $typed = $captured['comparable']['values'];
            self::assertNotSame($typed['integer_value'], $typed['text_value']);
            self::assertSame($captured['comparable'], $observer->capture($owner, $administrator)['comparable']);
            $owner->exec("SET @text_value = _utf8mb4'1' COLLATE utf8mb4_bin");
            self::assertNotSame($captured['comparable'], $observer->capture($owner, $administrator)['comparable']);
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('exactly one observed user-variable thread');
            $observer->inventory($administrator, 0);
        } finally {
            $client = null;
            $owner = null;
            $administrator = null;
            $server->stop();
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerVersions(): iterable
    {
        foreach (\Container\MySqlRelease::versions() as $version) {
            if (!str_starts_with($version, '5.6.')) {
                yield $version => [$version];
            }
        }
    }
}

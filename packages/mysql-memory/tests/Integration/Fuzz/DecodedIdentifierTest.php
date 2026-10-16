<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Execution;
use Fuzz\Target\DisposableMySql;
use mysqli;
use mysqli_result;
use mysqli_stmt;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class DecodedIdentifierTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.4.7'] as $release) {
            yield $release => [$release];
        }
    }

    #[DataProvider('providerReleases')]
    public function testOneStatementKeepsItsNamesWhenTheClientCannotSpellThem(string $release): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, null, $endpoint->port);
            $session = (new Instance($release))->connect();
            $setup = ['SET NAMES utf8mb4', 'CREATE DATABASE d', 'USE d', 'CREATE TABLE `猫` (`猫` INT)', 'INSERT INTO `猫` VALUES (7)'];
            array_map($native->query(...), $setup);
            array_map($session->query(...), $setup);
            $sql = 'SELECT `猫`+1, `猫` FROM `猫`';
            $prepared = $native->prepare($sql);
            self::assertInstanceOf(mysqli_stmt::class, $prepared);
            $statement = $session->analyze($sql, true)->statement;
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $statement);
            $session->text = 'unrelated invalid input';
            $execution = new Execution();
            $prepared->execute();
            $before = $execution->runIn($session, $statement);
            self::assertInstanceOf(ResultSet::class, $before);
            $beforeNative = $prepared->get_result();
            self::assertInstanceOf(mysqli_result::class, $beforeNative);
            self::assertSame((new Native())->result($beforeNative), (new Memory())->replies([$before])['results'][0]);
            $native->query('SET NAMES latin1');
            $session->query('SET NAMES latin1');
            $session->text = 'unrelated invalid input';
            $operation = $execution->operation($session, $statement, []);
            self::assertSame($statement, $operation->statement);
            $prepared->execute();
            $after = $execution->execute($session, $operation, []);
            self::assertInstanceOf(ResultSet::class, $after);
            $afterNative = $prepared->get_result();
            self::assertInstanceOf(mysqli_result::class, $afterNative);
            self::assertSame((new Native())->result($afterNative), (new Memory())->replies([$after])['results'][0]);
            $prepared->close();
            $native->close();
        } finally {
            $container->stop();
        }
    }
}

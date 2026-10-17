<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Catalogue;
use Fuzz\Structure\Choices;
use Fuzz\Structure\Construction;
use Fuzz\Structure\Metadata;
use Fuzz\Structure\Profiles;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use JsonException;
use mysqli;
use mysqli_result;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use SqlSemantics\Platform\MySql\Statement\Statement;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class TableIdentityStateTest extends TestCase
{
    /**
     * @return iterable<string, array{int, array<string, int|list<list<string>>>}>
     */
    public static function providerRoots(): iterable
    {
        yield 'generated ALTER TABLE' => [23, ['affected' => 0]];
        yield 'generated OPTIMIZE TABLE' => [151, ['rows' => [['d.ABCDE', 'optimize', 'note', 'Table does not support optimize, doing recreate + analyze instead'], ['d.ABCDE', 'optimize', 'status', 'OK']]]];
    }

    /**
     * @param array<string, int|list<list<string>>> $completion The successful completion or administration rows
     * @throws JsonException When the reviewed value inventory cannot be decoded
     * @throws ReflectionException When a generated constructor cannot be inspected
     */
    #[DataProvider('providerRoots')]
    public function testSameGeneratedTableIdentitySurvivesAStateTransition(int $index, array $completion): void
    {
        $catalogue = new Catalogue();
        $choices = new Choices('contract-20261015:' . $index);
        $choices->one($catalogue->roots());
        $node = (new Construction($choices, $catalogue, new Metadata()))->object($catalogue->roots()[$index], 6);
        self::assertInstanceOf(Statement::class, $node);
        $profile = (new Profiles())->select($node);
        $sql = (new Reference())->sql($node, $profile);
        $version = substr($profile->grammar->value, 6);
        $container = Testcontainers::run(new DisposableMySql($version));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $session = (new Instance($version))->connect();
                $session->text = 'unrelated invalid input';
                $before = (new Memory())->observe($session, $node);
                self::assertSame((new Native())->observe($native, $sql), $before);
                self::assertSame(1046, $before['error'][0] ?? null);
                $fixture = ['CREATE DATABASE d', 'USE d', 'CREATE TABLE b(a INT)', 'CREATE TABLE ABCDE(a INT)'];
                array_map(static fn (string $sql): bool|mysqli_result => $native->query($sql), $fixture);
                array_map($session->run(...), $fixture);
                $session->text = 'unrelated invalid input';
                $after = (new Memory())->observe($session, $node);

                self::assertSame((new Native())->observe($native, $sql), $after);
                self::assertArrayNotHasKey('error', $after);
                self::assertSame($completion, array_intersect_key($after['results'][0], $completion));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}

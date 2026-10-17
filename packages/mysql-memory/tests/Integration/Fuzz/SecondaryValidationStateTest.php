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
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\ValidationOption;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\SecondaryLoad;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class SecondaryValidationStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<AlterTable>, list<int>}>
     */
    public static function providerStructures(): iterable
    {
        $target = new QualifiedName(new Name('t'));
        $statements = [
            new AlterTable($target, [new ValidationOption(true), new SecondaryLoad(true)]),
            new AlterTable($target, [new ValidationOption(false), new SecondaryLoad(true)]),
            new AlterTable($target, [new ValidationOption(true), new SecondaryLoad(false)]),
            new AlterTable($target, [new ValidationOption(true), new ValidationOption(true), new SecondaryLoad(true)]),
            new AlterTable(new QualifiedName(new Name('t'), new Name('absent')), [new ValidationOption(true), new SecondaryLoad(true)]),
        ];
        foreach (['8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements, [1146, 3889, 1146]];
        }
    }

    /**
     * @param list<AlterTable> $statements The same structures before table creation, after creation and after removal
     * @param list<int> $expectedCodes The first request's native errors through the history
     */
    #[DataProvider('providerStructures')]
    public function testValidationKeepsSecondaryEngineStateChecks(string $release, array $statements, array $expectedCodes): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('CREATE DATABASE validation_domain');
                $native->select_db('validation_domain');
                $native->query('SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $session = (new Instance($release))->connect('root', 'localhost');
                $session->run('CREATE DATABASE validation_domain; USE validation_domain; SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $observed = array_map(static function (string $setup) use ($native, $session, $statements): array {
                    $native->query($setup);
                    $session->run($setup);
                    return array_map(static function (AlterTable $node) use ($native, $session): array {
                        $expected = (new Native())->observe($native, (new Reference())->sql($node, $session->semantics()->profile()));
                        $session->text = 'unrelated invalid input';
                        $actual = (new Memory())->observe($session, $node);
                        return [serialize($expected), serialize($actual), $expected['error'][0] ?? null];
                    }, $statements);
                }, ['DO 0', 'CREATE TABLE t (a INT)', 'DROP TABLE t']);
                $trials = array_merge(...$observed);

                self::assertSame(array_column($trials, 0), array_column($trials, 1));
                self::assertSame($expectedCodes, array_map(static fn (array $state): ?int => $state[0][2], $observed));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}

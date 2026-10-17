<?php

declare(strict_types=1);

namespace Tests\Integration\Definition;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use mysqli;
use mysqli_result;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\SetTableOptions;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\TextOptionKind;
use SqlSemantics\Platform\MySql\Statement\Table\Option\TextOption;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class JsonAttributeStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, AlterTable}>
     */
    public static function providerAttributes(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, new AlterTable(new QualifiedName(new Name('t'), new Name('placement')), [new SetTableOptions([new TextOption(TextOptionKind::SecondaryEngineAttribute, new Text('{"purpose":"testing"}'))])])];
        }
    }

    #[DataProvider('providerAttributes')]
    public function testSameAttributeStructureWorksAfterItsTableExists(string $release, AlterTable $alter): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $session = (new Instance($release))->connect('root', 'localhost');
                $sql = (new Reference())->sql($alter, $session->semantics()->profile());
                $fixtures = [['CREATE DATABASE placement'], ['CREATE TABLE placement.t(a INT)', 'INSERT INTO placement.t VALUES (2)'], ['DROP TABLE placement.t']];
                $reader = $session->semantics()->analyze('SELECT * FROM placement.t')->statement;
                self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $reader);
                $observed = array_map(static function (array $fixture) use ($native, $session, $alter, $sql, $reader): array {
                    array_map(static fn (string $text): bool|mysqli_result => $native->query($text), $fixture);
                    array_map($session->query(...), $fixture);
                    $expected = (new Native())->observe($native, $sql);
                    $session->text = 'unrelated invalid input';
                    $actual = (new Memory())->observe($session, $alter);
                    return [serialize([$expected, (new Native())->observe($native, 'SELECT * FROM placement.t')]), serialize([$actual, (new Memory())->observe($session, $reader)]), $expected['error'][0] ?? null];
                }, $fixtures);
                self::assertSame(array_column($observed, 0), array_column($observed, 1));
                self::assertSame([1146, null, 1146], array_column($observed, 2));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}

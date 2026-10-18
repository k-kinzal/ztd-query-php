<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Choices;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class LegacyIndexStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<M\Table\CreateIndex>}>
     */
    public static function providerStructures(): iterable
    {
        $choices = new Choices('legacy-index-directions-20261018');
        $structures = [];
        for ($trial = 0; $trial < 8; $trial++) {
            $part = new M\Table\Key\ColumnPart(new Name('a'), direction: $choices->one(M\Query\Direction::cases()));
            $structures[] = new M\Table\CreateIndex(new Name('idx'), new QualifiedName(new Name('t' . $trial)), [$part], M\Table\Key\IndexKind::FullText);
        }
        foreach (['5.6.51', '5.7.44'] as $release) {
            yield $release => [$release, $structures];
        }
    }

    /**
     * @param list<M\Table\CreateIndex> $structures Generated legacy indexes reused through table, row and index histories
     */
    #[DataProvider('providerStructures')]
    public function testTheSameIndexRetainsItsIgnoredDirectionThroughCatalogChanges(string $release, array $structures): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $native->query('CREATE DATABASE d');
            $native->query('USE d');
            $session = (new Instance($release, [], ['d']))->connect(database: 'd');
            $observations = array_map(static function (M\Table\CreateIndex $create, int $index) use ($session, $native): array {
                $memory = new Memory();
                $reference = new Reference();
                $sql = $reference->sql($create, $session->semantics()->profile());
                $history = array_map(static function (string $setup) use ($session, $native, $memory, $create, $sql): array {
                    $native->query($setup);
                    $session->query($setup);
                    $session->text = 'unrelated invalid input';
                    return [(new Native())->observe($native, $sql), $memory->observe($session, $create)];
                }, ['SET @run=0', 'CREATE TABLE t' . $index . '(a TEXT)', 'INSERT INTO t' . $index . " VALUES ('first value'), ('second value')", 'ALTER TABLE t' . $index . ' DROP INDEX idx', 'DROP TABLE t' . $index, 'CREATE TABLE t' . $index . '(a TEXT)']);
                self::assertSame([1146, null, 1061, null, 1146, null], array_map(static fn (array $pair): ?int => $pair[0]['error'][0] ?? null, $history));
                return $history;
            }, $structures, array_keys($structures));
            $observations = array_merge(...$observations);
            self::assertSame(array_map(serialize(...), array_column($observations, 0)), array_map(serialize(...), array_column($observations, 1)));
            self::assertSame('unrelated invalid input', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}

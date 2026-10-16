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
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey;
use SqlSemantics\Platform\MySql\Statement\Table\Key\References;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class ReferenceColumnsStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerReferences(): iterable
    {
        foreach (['9.0.1', '9.1.0'] as $release) {
            yield $release . ' column' => [$release, true];
            yield $release . ' table' => [$release, false];
        }
    }

    #[DataProvider('providerReferences')]
    public function testTheSameReferenceResolvesOnlyWhenTheParentPrimaryKeyIsAvailable(string $release, bool $inline): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $session = (new Instance($release))->connect();
                $reference = new References(new QualifiedName(new Name('p')));
                $column = new ColumnDefinition(new ColumnName(new Name('a')), new OrdinaryColumn(new Integral(IntegralKind::Int), references: $inline ? $reference : null));
                $node = new CreateTable(new QualifiedName(new Name('c')), $inline ? [$column] : [$column, new ForeignKey([new ColumnPart(new Name('a'))], $reference)]);
                $sql = (new Reference())->sql($node, $session->semantics()->profile());
                $fixtures = [
                    ['CREATE DATABASE d', 'USE d'], ['CREATE TABLE p(a INT)'],
                    ['ALTER TABLE p ADD PRIMARY KEY(a)'],
                    ['DROP TABLE c', 'SET foreign_key_checks=0'],
                    ['SET foreign_key_checks=1'],
                ];
                $observed = array_map(static function (array $fixture) use ($native, $session, $node, $sql): array {
                    array_map($native->query(...), $fixture);
                    array_map($session->query(...), $fixture);
                    $expected = (new Native())->observe($native, $sql);
                    $session->text = 'unrelated invalid input';
                    $actual = (new Memory())->observe($session, $node);

                    return [serialize($expected), serialize($actual), $expected['error'][0] ?? null];
                }, $fixtures);

                self::assertSame(array_column($observed, 0), array_column($observed, 1));
                self::assertSame([1239, 1239, null, 1239, null], array_column($observed, 2));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}

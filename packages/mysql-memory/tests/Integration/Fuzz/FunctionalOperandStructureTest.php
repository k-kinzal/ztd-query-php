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
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class FunctionalOperandStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<\SqlSemantics\Platform\MySql\Statement\Table\CreateTable>}>
     */
    public static function providerStructures(): iterable
    {
        $choices = new Choices('functional-operand-state-20261018');
        $structures = [];
        for ($trial = 0; $trial < 8; $trial++) {
            $operand = $trial % 2 === 0
                ? new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new NumberLiteral((string) $choices->integer(1, 100)), new NumberLiteral((string) $choices->integer(1, 100))])
                : new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredTimeZone(new NumberLiteral((string) $choices->integer(1, 100)), new \SqlSemantics\Platform\MySql\Statement\Literal\Text('text'));
            $part = new \SqlSemantics\Platform\MySql\Statement\Table\Key\Deferred\ExpressionPart($operand);
            $index = new \SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition($trial % 4 < 2 ? \SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind::Primary : \SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind::Index, [$part], new \SqlSemantics\Platform\MySql\Statement\Name\ColumnName(new Name('k')));
            $column = new \SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnName(new Name('a')), new \SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn(new \SqlSemantics\Platform\MySql\Statement\Type\Integral(\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int)));
            $structures[] = new \SqlSemantics\Platform\MySql\Statement\Table\CreateTable(new QualifiedName(new Name('t'), new Name('d')), [$column, $index], ifNotExists: true);
        }
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $structures];
        }
    }

    /**
     * @param list<\SqlSemantics\Platform\MySql\Statement\Table\CreateTable> $structures Identical definitions across destination histories
     */
    #[DataProvider('providerStructures')]
    public function testTheSameDefinitionCanBeUnusedOrPreparedByCatalogState(string $release, array $structures): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $session = (new Instance($release))->connect();
            $observations = array_map(static function (\SqlSemantics\Platform\MySql\Statement\Table\CreateTable $query) use ($session, $native): array {
                $sql = (new Reference())->sql($query, $session->semantics()->profile());
                $history = array_map(static function (string $setup) use ($session, $native, $query, $sql): array {
                    array_map(static function (string $step) use ($session, $native): void {
                        $native->query($step);
                        $session->query($step);
                    }, explode('; ', $setup));
                    $session->text = 'unrelated invalid input';
                    return [(new Native())->observe($native, $sql), (new Memory())->observe($session, $query)];
                }, ['SET @run=0', 'CREATE DATABASE d; CREATE TABLE d.t(a INT)', 'DROP TABLE d.t', 'CREATE TABLE d.t(a INT)', 'DROP DATABASE d']);
                $errors = array_map(static fn (array $pair): ?int => $pair[0]['error'][0] ?? null, $history);
                self::assertSame([1049, null, null, 1049], [$errors[0], $errors[1], $errors[3], $errors[4]]);
                self::assertContains($errors[2], [3756, 3800, 3998]);
                self::assertSame('unrelated invalid input', $session->text);
                return $history;
            }, $structures);
            $observations = array_merge(...$observations);
            self::assertSame(array_map(serialize(...), array_column($observations, 0)), array_map(serialize(...), array_column($observations, 1)));
            $native->close();
        } finally {
            $container->stop();
        }
    }
}

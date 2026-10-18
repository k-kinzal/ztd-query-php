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
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Platform\MySql\Statement\Query\With\With;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Platform\MySql\Statement\View\CreateView;
use SqlSemantics\Platform\MySql\Statement\View\ViewDefinition;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class ViewDependencyStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<CreateView>}>
     */
    public static function providerStructures(): iterable
    {
        $choices = new Choices('view-read-dependencies-20261018');
        $structures = [];
        for ($trial = 0; $trial < 8; $trial++) {
            $unused = new CommonTableExpression(new Name('unused'), [], new Select([], [new SelectExpression(new UserVariable(new Name('value' . $choices->integer(1, 1000))), new Name('x'))]));
            $body = new Select([], [new SelectExpression(new ColumnUse(new Name('a')))], new TableReference(new QualifiedName(new Name('t'), new Name('d'))));
            $structures[] = new CreateView(new ViewDefinition(new QualifiedName(new Name('v'), new Name('d')), new QueryExpression(new With(false, [$unused]), $body)), true);
        }
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $structures];
        }
    }

    /**
     * @param list<CreateView> $structures Identical views across missing, empty and populated source tables
     */
    #[DataProvider('providerStructures')]
    public function testTheSameViewPreservesItsUnusedDefinitionAndReachedTableDependency(string $release, array $structures): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $native->query('CREATE DATABASE d');
            $session = (new Instance($release, [], ['d']))->connect();
            $read = new QueryRoot(new Select([], [new SelectExpression(new ColumnUse(new Name('a')))], new TableReference(new QualifiedName(new Name('v'), new Name('d')))));
            $observations = array_map(static function (CreateView $create) use ($session, $native, $read): array {
                $reference = new Reference();
                $sql = $reference->sql($create, $session->semantics()->profile());
                $readSql = $reference->sql($read, $session->semantics()->profile());
                $history = array_map(static function (string $setup) use ($session, $native, $create, $sql, $read, $readSql): array {
                    array_map(static function (string $step) use ($session, $native): void {
                        $native->query($step);
                        $session->query($step);
                    }, explode('; ', $setup));
                    $session->text = 'unrelated invalid input';
                    return [[(new Native())->observe($native, $sql), (new Memory())->observe($session, $create)], [(new Native())->observe($native, $readSql), (new Memory())->observe($session, $read)]];
                }, ['DROP VIEW IF EXISTS d.v; DROP TABLE IF EXISTS d.t', 'CREATE TABLE d.t(a INT)', 'INSERT INTO d.t VALUES (3),(7)', 'DROP TABLE d.t', 'CREATE TABLE d.t(a INT); INSERT INTO d.t VALUES (11)']);
                self::assertSame([1146, null, null, 1146, null], array_map(static fn (array $pair): ?int => $pair[0][0]['error'][0] ?? null, $history));
                return array_merge(...$history);
            }, $structures);
            $observations = array_merge(...$observations);
            self::assertSame(array_map(serialize(...), array_column($observations, 0)), array_map(serialize(...), array_column($observations, 1)));
            self::assertSame('unrelated invalid input', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}

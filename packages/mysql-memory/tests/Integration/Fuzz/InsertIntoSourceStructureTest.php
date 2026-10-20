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
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class InsertIntoSourceStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, M\Routine\CreateProcedure}>
     */
    public static function providerStructures(): iterable
    {
        foreach (['file', 'variable'] as $kind) {
            $into = $kind === 'file' ? new M\Query\Into\IntoDumpfile(new M\Literal\Text('text')) : new M\Query\Into\IntoVariables([new M\Variable\UserVariable(new Name('result'))]);
            $predicate = new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]);
            $selection = new M\Routine\Program\DeferredSelection([new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))], where: $predicate, into: $into);
            $head = new M\Dml\Insert\InsertInto(false, null, false, new M\Dml\WriteTarget(new QualifiedName(new Name('t'), new Name('absent'))));
            $write = new M\Routine\Program\Write\DeferredInsertQuery($head, new M\Query\With\DeferredBody($selection));
            $branch = new M\Routine\Program\Flow\ConditionalBranch(new M\Variable\UserVariable(new Name('active')), [$write]);
            $create = new M\Routine\CreateProcedure(new QualifiedName(new Name('p')), new M\Routine\ParameterList([]), new M\Routine\Program\Flow\IfStatement([$branch]));
            foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
                yield $release . ':' . $kind => [$release, $create];
            }
        }
    }

    #[DataProvider('providerStructures')]
    public function testTheSameQueryWriteRetainsItsDestinationAcrossCatalogTransitions(string $release, M\Routine\CreateProcedure $create): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('CREATE DATABASE d');
                $native->select_db('d');
                $session = (new Instance($release, [], ['d']))->connect(database: 'd');
                $expected = (new Native())->observe($native, (new Reference())->sql($create, $session->semantics()->profile()));
                $session->text = 'unrelated invalid input';
                self::assertSame($expected, (new Memory())->observe($session, $create));
                self::assertArrayNotHasKey('error', $expected);
                $call = new M\Dml\ProcedureCall($create->name);
                $history = array_map(static function (string $setup) use ($native, $session, $call): array {
                    array_map(static function (string $sql) use ($native, $session): void {
                        $native->query($sql);
                        $session->query($sql);
                    }, explode('; ', $setup));
                    $expected = (new Native())->observe($native, (new Reference())->sql($call, $session->semantics()->profile()));
                    $session->text = 'unrelated invalid input';
                    return [serialize($expected), serialize((new Memory())->observe($session, $call)), $expected['error'][0] ?? null];
                }, ['SET @active=0', 'SET @active=1', 'CREATE DATABASE absent', 'CREATE TABLE absent.t(a INT)', 'INSERT INTO absent.t VALUES(1)', 'DROP TABLE absent.t', 'DROP DATABASE absent', 'SET @active=0']);
                self::assertSame([null, 1049, 1146, 1241, 1241, 1146, 1049, null], array_column($history, 2));
                self::assertSame(array_column($history, 0), array_column($history, 1));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}

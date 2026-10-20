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
final class UpdateTargetStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, M\Routine\CreateProcedure}>
     */
    public static function providerStructures(): iterable
    {
        $target = new QualifiedName(new Name('t'));
        $name = new Name('q');
        $query = new M\Query\Select([], [new M\Query\SelectExpression(new M\Name\ColumnUse(new Name('a')))], new M\Relation\TableReference($target));
        $common = new M\Query\With\With(false, [new M\Query\With\CommonTableExpression($name, [], $query)]);
        $json = new M\Relation\JsonTable(new M\Literal\StringLiteral(['[1]']), new M\Literal\StringLiteral(['$[*]']), [new M\Call\Json\OrdinalityColumn(new Name('a'))], $name);
        $sources = [
            'derived' => [null, [new M\Relation\DerivedTable($query, $name)]],
            'common' => [$common, [new M\Relation\TableReference(new QualifiedName($name))]],
            'json' => [null, [new M\Relation\TableReference($target), $json]],
        ];
        foreach ($sources as $kind => [$with, $tables]) {
            $write = new M\Routine\Program\Write\DeferredUpdate($with, false, false, $tables, [new M\Dml\Assignment(new M\Name\ColumnUse(new Name('a'), new QualifiedName($name)), new M\Literal\NumberLiteral('2'))]);
            $branch = new M\Routine\Program\Flow\ConditionalBranch(new M\Variable\UserVariable(new Name('active')), [$write]);
            $create = new M\Routine\CreateProcedure(new QualifiedName(new Name('p')), new M\Routine\ParameterList([]), new M\Routine\Program\Flow\IfStatement([$branch]));
            foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
                yield $release . ':' . $kind => [$release, $create];
            }
        }
    }

    #[DataProvider('providerStructures')]
    public function testTheSameReadOnlyDestinationRetainsDependenciesUntilInvocation(string $release, M\Routine\CreateProcedure $create): void
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
                }, ['SET @active=0', 'SET @active=1', 'CREATE TABLE t(a INT)', 'INSERT INTO t VALUES(1)', 'SET @active=0', 'DROP TABLE t', 'SET @active=1']);
                self::assertSame([null, 1146, 1288, 1288, null, null, 1146], array_column($history, 2));
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

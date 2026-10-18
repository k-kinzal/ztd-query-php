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
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use JsonException;
use mysqli;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class DeferredQueryWriteStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<M\Routine\CreateProcedure>}>
     * @throws JsonException When the constructor catalogue cannot be decoded
     * @throws ReflectionException When a public constructor cannot be inspected
     */
    public static function providerStructures(): iterable
    {
        $catalogue = new Catalogue();
        $metadata = new Metadata();
        $structures = [];
        for ($trial = 0; $trial < 8; $trial++) {
            $construction = new Construction(new Choices('query-write-source:' . $trial), $catalogue, $metadata);
            $number = $construction->object(M\Literal\NumberLiteral::class, 0);
            assert($number instanceof M\Literal\NumberLiteral);
            $row = new M\Expression\Row([new M\Name\ColumnUse(new Name('a')), $number]);
            $source = new M\Query\With\DeferredBody(new M\Routine\Program\DeferredSelection([new M\Routine\Program\DeferredProjection($row)], new M\Relation\TableReference(new QualifiedName(new Name('source')))));
            $head = new M\Dml\Insert\InsertInto($trial % 2 === 0, null, false, new M\Dml\WriteTarget(new QualifiedName(new Name('target'))));
            $write = new M\Routine\Program\Write\DeferredInsertQuery($head, $source);
            $branch = new M\Routine\Program\Flow\ConditionalBranch(new M\Variable\UserVariable(new Name('active')), [$write]);
            $structures[] = new M\Routine\CreateProcedure(new QualifiedName(new Name('p' . $trial)), new M\Routine\ParameterList([]), new M\Routine\Program\Flow\IfStatement([$branch]));
        }
        foreach (['5.6.51', '5.7.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $structures];
        }
    }

    /**
     * @param list<M\Routine\CreateProcedure> $structures The same retained writes across branch, target and source states
     */
    #[DataProvider('providerStructures')]
    public function testCompleteWritesPrepareOnlyWhenTheirStoredBranchIsReached(string $release, array $structures): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('CREATE DATABASE d');
                $native->select_db('d');
                $session = (new Instance($release, [], ['d']))->connect(database: 'd');
                $histories = array_map(static function (M\Routine\CreateProcedure $create) use ($native, $session): array {
                    $expected = (new Native())->observe($native, (new Reference())->sql($create, $session->semantics()->profile()));
                    $session->text = 'unrelated invalid input';
                    $actual = (new Memory())->observe($session, $create);
                    self::assertSame($expected, $actual);
                    self::assertArrayNotHasKey('error', $expected);
                    $call = new M\Dml\ProcedureCall($create->name);
                    $history = array_map(static function (string $setup) use ($native, $session, $call): array {
                        array_map(static function (string $sql) use ($native, $session): void {
                            $native->query($sql);
                            $session->query($sql);
                        }, explode('; ', $setup));
                        $expected = (new Native())->observe($native, (new Reference())->sql($call, $session->semantics()->profile()));
                        $session->text = 'unrelated invalid input';
                        $actual = (new Memory())->observe($session, $call);
                        return [serialize($expected), serialize($actual), $expected['error'][0] ?? null];
                    }, ['DROP TABLE IF EXISTS target,source; SET @active=0', 'SET @active=1', 'CREATE TABLE target(a INT)', 'CREATE TABLE source(b INT); INSERT INTO source VALUES(3)', 'ALTER TABLE source CHANGE b a INT', 'SET @active=0', 'DROP TABLE target', 'SET @active=1']);
                    self::assertSame([null, 1146, 1146, 1054, 1241, null, null, 1146], array_column($history, 2));
                    return $history;
                }, $structures);
                $observed = array_merge(...$histories);
                self::assertSame(array_column($observed, 0), array_column($observed, 1));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}

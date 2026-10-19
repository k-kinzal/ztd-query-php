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
final class AlterStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, M\Routine\CreateProcedure, int}>
     */
    public static function providerStructures(): iterable
    {
        $row = static fn (): M\Expression\Row => new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]);
        $cases = [
            'row' => [new M\Routine\Program\Write\DeferredDefaultSetting(new M\Name\ColumnName(new Name('a')), $row()), 3771],
            'arithmetic' => [new M\Routine\Program\Write\DeferredDefaultSetting(new M\Name\ColumnName(new Name('a')), new M\Routine\Program\Expression\DeferredArithmetic(M\Expression\Operator\ArithmeticOperator::Plus, new M\Literal\NumberLiteral('1'), $row())), 1241],
            'validation' => [new M\Alter\Modifier\ValidationOption(true), 1221],
        ];
        foreach ($cases as $case => [$command, $code]) {
            $write = new M\Routine\Program\Write\DeferredAlterTable(new QualifiedName(new Name('t')), [$command]);
            $branch = new M\Routine\Program\Flow\ConditionalBranch(new M\Variable\UserVariable(new Name('active')), [$write]);
            $create = new M\Routine\CreateProcedure(new QualifiedName(new Name('p')), new M\Routine\ParameterList([]), new M\Routine\Program\Flow\IfStatement([$branch]));
            foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
                yield $release . ':' . $case => [$release, $create, $code];
            }
        }
    }

    #[DataProvider('providerStructures')]
    public function testTheSameInstructionRetainsItsInputsAcrossInvocationStates(string $release, M\Routine\CreateProcedure $create, int $code): void
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
                self::assertSame([null, $code === 1221 ? 1221 : 1146, $code, $code, null, null, $code === 1221 ? 1221 : 1146], array_column($history, 2));
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

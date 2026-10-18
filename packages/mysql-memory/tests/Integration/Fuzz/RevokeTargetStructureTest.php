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
final class RevokeTargetStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<M\Routine\CreateProcedure>}>
     */
    public static function providerStructures(): iterable
    {
        foreach (['5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $choices = new Choices('routine-revocation-targets-' . $release);
            $structures = [];
            for ($trial = 0; $trial < 12; $trial++) {
                $kind = $choices->one([M\Account\Privilege\Level\ObjectKind::Procedure, M\Account\Privilege\Level\ObjectKind::Function]);
                $level = match ($trial % 3) {
                    0 => new M\Account\Privilege\Level\CurrentDatabaseLevel(),
                    1 => new M\Account\Privilege\Level\DatabaseLevel(new Name('absent')),
                    2 => new M\Account\Privilege\Level\GlobalLevel(),
                };
                $privilege = $level instanceof M\Account\Privilege\Level\GlobalLevel && $release !== '5.7.44'
                    ? new M\Account\Privilege\Item\StaticPrivilege(M\Account\Privilege\Item\PrivilegeKind::Execute)
                    : $choices->one([new M\Account\Privilege\Item\AllPrivileges(), new M\Account\Privilege\Item\StaticPrivilege(M\Account\Privilege\Item\PrivilegeKind::Execute)]);
                $modern = in_array($release, ['8.4.7', '9.1.0'], true);
                $definition = new M\Routine\Program\Write\DeferredRevokePrivileges($modern && $choices->integer(0, 1) === 1, [$privilege], $kind, $level, [new M\Account\User\UserSpecification(new M\Name\AccountName(new Name('missing')))], $modern && $choices->integer(0, 1) === 1);
                $branch = new M\Routine\Program\Flow\ConditionalBranch(new M\Variable\UserVariable(new Name('run')), [$definition]);
                $structures[] = new M\Routine\CreateProcedure(new QualifiedName(new Name('p' . $trial)), new M\Routine\ParameterList([]), new M\Routine\Program\Flow\IfStatement([$branch]));
            }
            yield $release => [$release, $structures];
        }
    }

    /**
     * @param list<M\Routine\CreateProcedure> $structures Complete definitions reused across invocation and transaction states
     */
    #[DataProvider('providerStructures')]
    public function testSameDefinitionsRetainStoredConditionsAndImplicitCommit(string $release, array $structures): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $native->query('CREATE DATABASE d');
            $native->query('USE d');
            $session = (new Instance($release, [], ['d']))->connect(database: 'd');
            $native->query('CREATE TABLE t(a INT)');
            $session->query('CREATE TABLE t(a INT)');
            $observations = array_map(static function (M\Routine\CreateProcedure $create) use ($native, $session): array {
                $memory = new Memory();
                $reference = new Reference();
                $session->text = 'unrelated invalid input';
                $definition = [(new Native())->observe($native, $reference->sql($create, $session->semantics()->profile())), $memory->observe($session, $create)];
                self::assertArrayNotHasKey('error', $definition[0]);
                $call = new M\Dml\ProcedureCall($create->name);
                $sql = $reference->sql($call, $session->semantics()->profile());
                $history = array_map(static function (string $setup) use ($native, $session, $memory, $call, $sql): array {
                    foreach (explode('; ', $setup) as $step) {
                        $native->query($step);
                        $session->query($step);
                    }
                    $session->text = 'unrelated invalid input';
                    return [(new Native())->observe($native, $sql), $memory->observe($session, $call)];
                }, ['TRUNCATE t; SET @run=0; START TRANSACTION; INSERT INTO t VALUES(1)', 'SET @run=1', 'SET @run=0']);
                self::assertSame([null, 1144, null], array_map(static fn (array $pair): ?int => $pair[0]['error'][0] ?? null, $history));
                $native->query('ROLLBACK');
                $session->query('ROLLBACK');
                $session->text = 'unrelated invalid input';
                $read = new M\QueryRoot(new M\Query\Select([], [new M\Query\Star()], new M\Relation\TableReference(new QualifiedName(new Name('t')))));
                $committed = [(new Native())->observe($native, 'SELECT * FROM t'), $memory->observe($session, $read)];
                self::assertSame([['1']], $committed[0]['results'][0]['rows']);
                return [$definition, ...$history, $committed];
            }, $structures);
            $pairs = array_merge(...$observations);
            self::assertSame(array_map(serialize(...), array_column($pairs, 0)), array_map(serialize(...), array_column($pairs, 1)));
            self::assertSame('unrelated invalid input', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}

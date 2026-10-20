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
final class RoleGrantStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, M\Routine\CreateProcedure}>
     */
    public static function providerStructures(): iterable
    {
        foreach (['anonymous-role', 'anonymous-recipient', 'self'] as $kind) {
            $roles = [new M\Account\Privilege\Item\GrantedRole(new M\Name\AccountName(new Name('r')))];
            $users = [new M\Name\AccountName(new Name($kind === 'anonymous-recipient' ? '' : 'u'))];
            if ($kind === 'anonymous-role') {
                $roles[] = new M\Account\Privilege\Item\GrantedRole(new M\Name\AccountName(new Name('')));
            } elseif ($kind === 'self') {
                $users[] = new M\Name\AccountName(new Name('r'));
            }
            $write = new M\Routine\Program\Write\DeferredGrantRoles($roles, $users, true);
            $branch = new M\Routine\Program\Flow\ConditionalBranch(new M\Variable\UserVariable(new Name('active')), [$write]);
            $create = new M\Routine\CreateProcedure(new QualifiedName(new Name('p')), new M\Routine\ParameterList([]), new M\Routine\Program\Flow\IfStatement([$branch]));
            foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
                yield $release . ':' . $kind => [$release, $create];
            }
        }
    }

    #[DataProvider('providerStructures')]
    public function testTheSameStoredGrantRetainsItsOperandsAcrossAccountStateChanges(string $release, M\Routine\CreateProcedure $create): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('SET NAMES utf8mb4');
                $native->query('CREATE DATABASE d');
                $native->select_db('d');
                $session = (new Instance($release, [], ['d']))->connect(database: 'd');
                $session->query('SET NAMES utf8mb4');
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
                }, ['SET @active=0', 'SET @active=1', 'CREATE USER u', 'CREATE ROLE r', 'CREATE USER \'\'', 'DROP USER u', 'SET @active=0']);
                self::assertInstanceOf(M\Routine\Program\Flow\IfStatement::class, $create->body);
                self::assertInstanceOf(M\Routine\Program\Write\DeferredGrantRoles::class, $create->body->branches[0]->statements[0]);
                $grant = $create->body->branches[0]->statements[0];
                $anonymous = $grant->users[0] instanceof M\Name\AccountName && $grant->users[0]->username() === '';
                $reached = count($grant->roles) === 2 ? 3524 : 4027;
                self::assertSame($anonymous ? [null, 3876, 3876, 3876, 3876, 3876, null] : [null, 3523, 3523, $reached, $reached, 3523, null], array_column($history, 2));
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

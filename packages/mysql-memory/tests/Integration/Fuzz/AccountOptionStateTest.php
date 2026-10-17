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
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class AccountOptionStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release];
        }
    }

    #[DataProvider('providerReleases')]
    public function testSameAccountStructuresSurviveCreationAlterationAndRemoval(string $release): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $session = (new Instance($release))->connect();
            $name = new M\Name\AccountName(new Name('typed_account'));
            $users = [new M\Account\User\UserSpecification($name)];
            $options = [new M\Account\Option\AccountOption(M\Account\Option\AccountOptionKind::ExpireInterval, new M\Literal\Numeral('00065535')), new M\Account\Option\AccountOption(M\Account\Option\AccountOptionKind::LockTime, new M\Literal\Numeral('00032767')), new M\Account\Option\AccountOption(M\Account\Option\AccountOptionKind::FailedLoginAttempts, new M\Literal\Numeral('7fff', true)), new M\Account\Option\AccountOption(M\Account\Option\AccountOptionKind::HistoryCount, new M\Literal\Numeral('10000000000000000', true)), new M\Account\Option\AccountOption(M\Account\Option\AccountOptionKind::ReuseInterval, new M\Literal\Numeral('4294967296'))];
            $tls = new M\Account\Option\TlsRequirement(M\Account\Option\TlsKind::Specified, [new M\Account\Option\TlsCondition(M\Account\Option\TlsAttribute::Issuer, new M\Literal\Text('issuer')), new M\Account\Option\TlsCondition(M\Account\Option\TlsAttribute::Subject, new M\Literal\Text('subject'))]);
            $create = new M\Account\CreateUser(false, $users, tls: $tls, options: $options);
            $alter = new M\Account\AlterUser(false, $users, options: $options);
            $drop = new M\Account\DropUser(false, [$name]);
            $show = new M\Utility\Show\Server\ShowCreateUser($name);
            $observed = array_map(static function (M\Statement $node) use ($native, $session): array {
                $expected = (new Native())->observe($native, (new Reference())->sql($node, $session->semantics()->profile()));
                $session->text = 'unrelated invalid input';
                $actual = (new Memory())->observe($session, $node);

                return [serialize($expected), serialize($actual)];
            }, [$alter, $create, $show, $alter, $show, $create, $drop, $alter, $create, $show]);

            self::assertSame(array_column($observed, 0), array_column($observed, 1));
            self::assertSame('unrelated invalid input', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}

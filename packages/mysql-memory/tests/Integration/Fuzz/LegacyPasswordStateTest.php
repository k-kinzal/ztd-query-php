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
use SqlSemantics\Platform\MySql\Statement\Account\CreateUser;
use SqlSemantics\Platform\MySql\Statement\Account\DropUser;
use SqlSemantics\Platform\MySql\Statement\Account\ExpireUserPasswords;
use SqlSemantics\Platform\MySql\Statement\Account\Password;
use SqlSemantics\Platform\MySql\Statement\Account\PasswordFunction;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\PasswordAssignment;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\UserAssignment;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Identifier\Name;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class LegacyPasswordStateTest extends TestCase
{
    /**
     * @return iterable<string, array{list<Statement>, int}>
     */
    public static function providerHistories(): iterable
    {
        $name = new AccountName(new Name('sixteen_lettersx'), new Name('localhost'));
        $create = new CreateUser(false, [new UserSpecification($name)]);
        $drop = new DropUser(false, [$name]);
        $expiration = new ExpireUserPasswords([$name]);
        $assignment = new SetVariables([new UserAssignment(new UserVariable(new Name('touched')), new NumberLiteral('7')), new PasswordAssignment(new Password(new Text('pw'), PasswordFunction::Password), $name)]);
        yield 'expiration' => [[$expiration, $create, $expiration, $drop, $expiration], 1396];
        yield 'assignment list' => [[$assignment, $create, $assignment, $drop, $assignment], 1133];
    }

    /**
     * @param list<Statement> $history The original structures before account creation, after creation and after removal
     */
    #[DataProvider('providerHistories')]
    public function testSameLegacyRequestTracksAccountExistence(array $history, int $missing): void
    {
        $container = Testcontainers::run(new DisposableMySql('5.6.51'));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $session = (new Instance('5.6.51'))->connect('root', 'localhost');
                $session->run('SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $observed = array_map(static function (Statement $node) use ($native, $session): array {
                    $expected = (new Native())->observe($native, (new Reference())->sql($node, $session->semantics()->profile()));
                    $session->text = 'unrelated invalid input';
                    $actual = (new Memory())->observe($session, $node);
                    return [serialize($expected), serialize($actual), $expected['error'][0] ?? null];
                }, $history);

                self::assertSame(array_column($observed, 0), array_column($observed, 1));
                self::assertSame([$missing, null, null, null, $missing], array_column($observed, 2));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}

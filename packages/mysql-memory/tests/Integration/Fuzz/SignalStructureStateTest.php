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
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionItemName;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SignalItem;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Identifier\Name;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class SignalStructureStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, int|null}>
     */
    public static function providerStates(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release . ': warning' => [$release, '01000', null];
            yield $release . ': exception' => [$release, '45000', 1644];
        }
    }

    #[DataProvider('providerStates')]
    public function testSameConditionDistinguishesOperandStateFromAnIntentionalSqlCondition(string $release, string $state, ?int $error): void
    {
        $node = new Signal(new SqlState(new Text($state)), [new SignalItem(ConditionItemName::MessageText, new UserVariable(new Name('message')))]);
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $session = (new Instance($release))->connect();
                $session->text = 'unrelated invalid input';
                $sql = (new Reference())->sql($node, $session->semantics()->profile());
                $first = (new Memory())->observe($session, $node);
                self::assertSame((new Native())->observe($native, $sql), $first);
                self::assertSame(1231, $first['error'][0] ?? null);
                $native->query("SET @message = 'first condition'");
                $session->query("SET @message = 'first condition'");
                $session->text = 'unrelated invalid input';
                $second = (new Memory())->observe($session, $node);
                self::assertSame((new Native())->observe($native, $sql), $second);
                self::assertSame($error, $second['error'][0] ?? null);
                $native->query("SET @message = 'second condition'");
                $session->query("SET @message = 'second condition'");
                $session->text = 'unrelated invalid input';
                $third = (new Memory())->observe($session, $node);
                self::assertSame((new Native())->observe($native, $sql), $third);
                self::assertSame('second condition', $third['warnings'][0][2] ?? null);
                self::assertSame($error, $third['error'][0] ?? null);
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}

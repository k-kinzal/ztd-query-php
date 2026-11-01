<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile;

use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use WeakReference;

#[CoversClass(Connection::class)]
#[Small]
final class ConnectionTest extends TestCase
{
    public function testTheAccountAndIdAreThoseTheSessionConnectedWith(): void
    {
        $instance = new Instance();
        $instance->connect();
        $session = $instance->connect('alice', 'example.com');
        $result = $session->query('SELECT CONNECTION_ID(), USER()')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[(string) $session->id, 'alice@example.com']], $result->rows);
    }

    public function testTheParametersAreTheValuesBoundToTheMarkers(): void
    {
        $session = (new Instance())->connect();
        $result = $session->run('SELECT ? + 1', [[5, Domain::integer()]], true)[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['6']], $result->rows);
    }

    public function testTheVariablesAreThoseOfTheSession(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET @a = 41');
        $result = $session->query('SELECT @a + 1')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['42']], $result->rows);
    }

    public function testSessionAnswersTheSessionWhileItLives(): void
    {
        $session = (new Instance())->connect();
        $context = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame([$session, null], [(new Connection($session->variables, $context, session: WeakReference::create($session)))->session(), (new Connection($session->variables, $context))->session()]);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Session\State;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\State\Preparation;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Preparation::class)]
#[Small]
final class PreparationTest extends TestCase
{
    public function testExecutionRestoresDerivedDomainsAfterAnError(): void
    {
        $session = (new Instance())->connect();
        $session->query("PREPARE s FROM 'SELECT ?+9223372036854775807'; SET @a=1");
        $previous = [0 => Domain::double()];
        $session->preparation->domains = $previous;
        $reply = $session->run('EXECUTE s USING @a')[0];

        self::assertInstanceOf(SqlError::class, $reply);
        self::assertSame(1690, $reply->getCode());
        self::assertSame($previous, $session->preparation->domains);
        self::assertArrayHasKey('s', $session->preparation->named);
    }

    public function testSessionsKeepNamedPreparationsSeparate(): void
    {
        $instance = new Instance();
        $first = $instance->connect();
        $second = $instance->connect();
        $first->query("PREPARE s FROM 'SELECT 1'");

        self::assertArrayHasKey('s', $first->preparation->named);
        self::assertSame([], $second->preparation->named);
        self::assertSame([], $second->preparation->domains);
    }
}

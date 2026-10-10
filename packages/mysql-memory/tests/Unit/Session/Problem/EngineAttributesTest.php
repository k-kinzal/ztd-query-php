<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Problem;

use MySqlMemory\Instance;
use MySqlMemory\Session\Problem\EngineAttributes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(EngineAttributes::class)]
#[Medium]
final class EngineAttributesTest extends TestCase
{
    public function testCheckReportsTheBytePositionBeforeOpeningTheTable(): void
    {
        $session = (new Instance())->connect();
        $session->run("CREATE INDEX i ON missing(c) ENGINE_ATTRIBUTE 'text'");

        self::assertSame([['Error', 3980, 'Invalid json attribute, error: "Invalid value." at pos 1: \'ext\'']], $session->diagnostics->conditions);
    }

    public function testCheckAcceptsAnEmptyAttributeAndAnyJsonValue(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze("CREATE TABLE t(a INT ENGINE_ATTRIBUTE '', b INT ENGINE_ATTRIBUTE 'null', KEY(a) ENGINE_ATTRIBUTE '[]') ENGINE_ATTRIBUTE '{}' SECONDARY_ENGINE_ATTRIBUTE '1'");

        (new EngineAttributes())->check($operation->statement);

        self::assertSame([], $session->diagnostics->conditions);
    }
}

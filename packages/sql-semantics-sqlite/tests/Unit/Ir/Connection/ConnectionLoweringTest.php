<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Connection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Connection\ConnectionLowering;
use SqlSemantics\Platform\Sqlite\Ir\Connection\Detach;
use SqlSemantics\Platform\Sqlite\Ir\Connection\DetachTarget;
use SqlSemantics\Platform\Sqlite\Model\Contract\SearchPath;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(ConnectionLowering::class)]
#[Small]
final class ConnectionLoweringTest extends TestCase
{
    public function testLowerResolvesACaseEquivalentAttachment(): void
    {
        $semantics = new Semantics(searchPath: new SearchPath('main', 'archive'));
        $value = $semantics->analyze("DETACH DATABASE 'ARChive'");

        self::assertInstanceOf(Detach::class, $value);
        self::assertInstanceOf(DetachTarget::class, $value->target);
        self::assertSame('archive', $value->target->schema->value);
        self::assertSame("DETACH 'ARChive'", $value->toString());
    }
}

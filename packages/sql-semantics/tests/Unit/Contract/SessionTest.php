<?php

declare(strict_types=1);

namespace Tests\Unit\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\Session;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;

#[CoversClass(Session::class)]
#[Medium]
final class SessionTest extends TestCase
{
    public function testSessionOfAPlatformIsCarriedByTheContextItIsGivenTo(): void
    {
        $session = new Settings(Collation::known('latin1_swedish_ci'));

        $context = (new Semantics(Dialect::MySql))->context([], true, null, $session);

        self::assertSame($session, $context->session);
    }

    public function testSessionIsAbsentFromAContextGivenNone(): void
    {
        self::assertNull((new Semantics(Dialect::MySql))->context([])->session);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\User\SessionUser;

#[CoversClass(SessionUser::class)]
#[Medium]
final class SessionUserTest extends TestCase
{
    public function testRenderWritesTheFunction(): void
    {
        self::assertSame("ALTER USER USER() IDENTIFIED BY 'x'", (new Semantics(Dialect::MySql))->analyze("alter user user ( ) identified by 'x'")->toString());
    }
}

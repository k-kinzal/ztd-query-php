<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Routine\Program\PrivilegeGrant;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredGrantAs;

#[CoversClass(PrivilegeGrant::class)]
#[Medium]
final class PrivilegeGrantTest extends TestCase
{
    public function testReadDistinguishesImmediateAndDeferredGrantForms(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        $deferred = $semantics->analyze('CREATE PROCEDURE p() GRANT ALL ON d.* TO u AS CURRENT_USER')->statement;
        $ordinary = $semantics->analyze('CREATE PROCEDURE p() GRANT ALL ON *.* TO u AS CURRENT_USER')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $deferred);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $ordinary);
        self::assertInstanceOf(DeferredGrantAs::class, $deferred->body);
        self::assertInstanceOf(GrantPrivileges::class, $ordinary->body);
        self::assertSame('CREATE PROCEDURE p() GRANT r TO u', $semantics->analyze('CREATE PROCEDURE p() GRANT r TO u')->toString());
    }
}

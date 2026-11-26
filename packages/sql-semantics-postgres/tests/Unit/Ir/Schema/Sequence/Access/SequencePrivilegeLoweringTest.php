<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\GrantSequenceAccess;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequencePrivilegeLowering;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(SequencePrivilegeLowering::class)]
final class SequencePrivilegeLoweringTest extends TestCase
{
    public function testRightsPreserveAllThreeSequencePrivileges(): void
    {
        $semantics = new Semantics();
        $value = $semantics->analyze('GRANT SELECT, UPDATE, USAGE ON SEQUENCE ticket TO PUBLIC', $semantics->context(['CREATE SEQUENCE ticket']));
        self::assertInstanceOf(GrantSequenceAccess::class, $value);
        self::assertSame(['SELECT', 'UPDATE', 'USAGE'], array_column($value->access->rights, 'value'));
    }

    public function testGranteesPreserveSessionAndUniversalRoles(): void
    {
        $semantics = new Semantics();
        $value = $semantics->analyze(
            'GRANT USAGE ON SEQUENCE ticket TO CURRENT_ROLE, SESSION_USER, PUBLIC',
            $semantics->context(['CREATE SEQUENCE ticket'])
        );
        self::assertInstanceOf(GrantSequenceAccess::class, $value);
        self::assertSame('CURRENT_USER, SESSION_USER, PUBLIC', $value->access->recipientsSql());
    }
}

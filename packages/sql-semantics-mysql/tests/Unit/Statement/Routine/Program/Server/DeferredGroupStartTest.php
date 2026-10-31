<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredGroupStart;

#[CoversClass(DeferredGroupStart::class)]
#[Medium]
final class DeferredGroupStartTest extends TestCase
{
    public function testDeriveProgramRetainsCredentialsWithoutReportingAnInvocationCondition(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("CREATE PROCEDURE p() START GROUP_REPLICATION PASSWORD = 'p'");
        self::assertSame([], $operation->diagnostics());
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation);
        self::assertInstanceOf(DeferredGroupStart::class, $operation->body);
    }

    public function testRenderKeepsEveryCredential(): void
    {
        self::assertSame("CREATE PROCEDURE p() START GROUP_REPLICATION USER = 'u', USER = '', PASSWORD = 'p'", (new Semantics(Dialect::MySql))->analyze("CREATE PROCEDURE p() START GROUP_REPLICATION USER = 'u', USER = '', PASSWORD = 'p'")->toString());
    }

    public function testProblemReportsTheRetainedCredentialCondition(): void
    {
        $request = new DeferredGroupStart([new \SqlSemantics\Platform\MySql\Statement\Replication\Group\CredentialOption(\SqlSemantics\Platform\MySql\Statement\Replication\Group\Credential::Password, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('p'))]);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError::GroupUserMissing, $request->problem()->error);
    }
}

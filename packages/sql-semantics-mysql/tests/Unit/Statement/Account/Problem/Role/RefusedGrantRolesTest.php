<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Problem\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\Role\RefusedGrantRoles;

#[CoversClass(RefusedGrantRoles::class)]
#[Small]
final class RefusedGrantRolesTest extends TestCase
{
    public function testMessageDescribesTheFixedAccountDomain(): void
    {
        $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() GRANT r TO r')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $create);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredGrantRoles::class, $create->body);
        self::assertSame('GRANT cannot use anonymous accounts or grant an explicit account to itself.', $create->body->problem()->message());
    }
}

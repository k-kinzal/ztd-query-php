<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Call\Structure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Call\Structure\CallRoles;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(CallRoles::class)]
#[Medium]
final class CallRolesTest extends TestCase
{
    public function testProblemFollowsOnlyContextualizedDefinitionsAndClauses(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('WITH c AS (SELECT ``(ABS())) SELECT 7');
        self::assertInstanceOf(M\QueryRoot::class, $operation);
        self::assertNull((new CallRoles())->problem($operation));
        self::assertInstanceOf(M\Query\QueryExpression::class, $operation->query);
        self::assertInstanceOf(M\Query\With\With::class, $operation->query->with);
        self::assertSame("Incorrect routine name ''", (new CallRoles())->problem($operation->query->with->tables[0]->query)?->message());
        self::assertSame("Incorrect parameter count in the call to native function 'ABS'", (new CallRoles())->problem(new M\Routine\Program\Expression\Call\DormantCall(new Name('ABS')))?->message());
    }
}

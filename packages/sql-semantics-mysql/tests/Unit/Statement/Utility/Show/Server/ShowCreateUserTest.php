<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowCreateUser;

#[CoversClass(ShowCreateUser::class)]
#[Medium]
final class ShowCreateUserTest extends TestCase
{
    public function testDeriveStatementDependsOnTheCurrentUser(): void
    {
        $show = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SHOW CREATE USER CURRENT_USER');
        self::assertInstanceOf(ShowCreateUser::class, $show->statement);
        self::assertFalse($show->shape()?->complete());
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW CREATE USER CURRENT_USER', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SHOW CREATE USER CURRENT_USER')->toString());
    }
}

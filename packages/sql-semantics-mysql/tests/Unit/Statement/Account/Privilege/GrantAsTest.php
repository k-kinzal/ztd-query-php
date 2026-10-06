<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantAs;

#[CoversClass(GrantAs::class)]
#[Medium]
final class GrantAsTest extends TestCase
{
    public function testRenderWritesTheAccountAndRoles(): void
    {
        self::assertSame('GRANT SELECT ON *.* TO u AS a WITH ROLE ALL EXCEPT r', (new Semantics(Dialect::MySql))->analyze('grant select on *.* to u as a with role all except r')->toString());
    }
}

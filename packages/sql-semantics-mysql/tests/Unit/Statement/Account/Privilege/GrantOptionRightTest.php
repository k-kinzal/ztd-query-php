<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantOptionRight;

#[CoversClass(GrantOptionRight::class)]
#[Medium]
final class GrantOptionRightTest extends TestCase
{
    public function testRenderWritesGrantOption(): void
    {
        self::assertSame('GRANT ALL ON db.* TO u WITH GRANT OPTION', (new Semantics(Dialect::MySql))->analyze('grant all on db.* to u with grant option')->toString());
    }
}

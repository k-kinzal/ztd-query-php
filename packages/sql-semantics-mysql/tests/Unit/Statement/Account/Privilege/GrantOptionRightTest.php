<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantOptionRight;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(GrantOptionRight::class)]
#[Medium]
final class GrantOptionRightTest extends TestCase
{
    public function testRenderWritesGrantOption(): void
    {
        self::assertSame('GRANT ALL ON db.* TO u WITH GRANT OPTION', (new Inspection())->inspect('grant all on db.* to u with grant option')->toString());
    }
}

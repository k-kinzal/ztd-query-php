<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege\Item;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(AllPrivileges::class)]
#[Medium]
final class AllPrivilegesTest extends TestCase
{
    public function testRenderWritesAll(): void
    {
        self::assertSame('GRANT ALL ON *.* TO u', (new Inspection())->inspect('grant all privileges on *.* to u')->toString());
    }
}

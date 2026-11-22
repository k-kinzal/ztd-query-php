<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege\Level;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\DatabaseLevel;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(DatabaseLevel::class)]
#[Medium]
final class DatabaseLevelTest extends TestCase
{
    public function testDescribeNamesTheDatabase(): void
    {
        self::assertSame('database shop', (new DatabaseLevel(new Name('shop')))->describe());
    }

    public function testRenderWritesTheDatabase(): void
    {
        self::assertSame('GRANT SELECT ON `my db`.* TO u', (new Inspection())->inspect('grant select on `my db`.* to u')->toString());
    }
}

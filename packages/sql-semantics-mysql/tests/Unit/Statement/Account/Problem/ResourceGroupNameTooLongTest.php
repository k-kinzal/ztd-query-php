<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\ResourceGroupNameTooLong;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ResourceGroupNameTooLong::class)]
#[Small]
final class ResourceGroupNameTooLongTest extends TestCase
{
    public function testMessageDescribesTheConvertedNameWithoutDiscardingTheOperand(): void
    {
        $name = new Name(str_repeat('😀', 65));
        $problem = new ResourceGroupNameTooLong($name);

        self::assertSame("Identifier name '" . str_repeat('?', 65) . "' is too long", $problem->message());
        self::assertSame($name, $problem->name);
    }
}

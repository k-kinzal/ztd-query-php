<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Modifier;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlterModifier;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\ValidationOption;

#[CoversNothing]
#[Small]
final class AlterModifierTest extends TestCase
{
    public function testAModifierIsAnAction(): void
    {
        self::assertContains(AlterCommand::class, class_implements(AlterModifier::class));
        self::assertContains(AlterModifier::class, class_implements(ValidationOption::class));
    }
}

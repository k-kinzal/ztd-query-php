<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\Force;

#[CoversNothing]
#[Small]
final class AlterCommandTest extends TestCase
{
    public function testDeriveCommandIsDeclaredByEveryAction(): void
    {
        self::assertContains(AlterCommand::class, class_implements(Force::class));
    }
}

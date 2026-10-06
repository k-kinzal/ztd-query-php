<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Partition;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\RemovePartitioning;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\TrailingCommand;

#[CoversNothing]
#[Small]
final class TrailingCommandTest extends TestCase
{
    public function testATrailingCommandIsAnAction(): void
    {
        self::assertContains(AlterCommand::class, class_implements(TrailingCommand::class));
        self::assertContains(TrailingCommand::class, class_implements(RemovePartitioning::class));
    }
}

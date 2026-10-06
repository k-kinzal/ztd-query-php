<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Partition;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\DropPartition;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\StandaloneCommand;

#[CoversNothing]
#[Small]
final class StandaloneCommandTest extends TestCase
{
    public function testAStandaloneCommandIsAnAction(): void
    {
        self::assertContains(AlterCommand::class, class_implements(StandaloneCommand::class));
        self::assertContains(StandaloneCommand::class, class_implements(DropPartition::class));
    }
}

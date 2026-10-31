<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Spatial;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialCommand;

#[CoversClass(SpatialCommand::class)]
#[Small]
final class SpatialCommandTest extends TestCase
{
    public function testCasesHoldTheKeywords(): void
    {
        self::assertSame(['CREATE', 'CREATE OR REPLACE', 'DROP'], array_column(SpatialCommand::cases(), 'value'));
    }
}

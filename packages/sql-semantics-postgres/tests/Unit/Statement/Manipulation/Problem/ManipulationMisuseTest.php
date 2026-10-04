<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Manipulation\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem\ManipulationMisuse::class)]
#[Small]
final class ManipulationMisuseTest extends TestCase
{
    public function testMessageFillsTheSubjectsInOrder(): void
    {
        self::assertSame('column "x" of relation "t" does not exist', (new \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem\ManipulationMisuse(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem\ManipulationMisuseRule::UnknownTargetColumn, 'x', 't'))->message());
    }
}

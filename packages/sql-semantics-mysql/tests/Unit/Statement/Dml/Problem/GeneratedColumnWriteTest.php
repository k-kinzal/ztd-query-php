<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\GeneratedColumnWrite;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(GeneratedColumnWrite::class)]
#[Small]
final class GeneratedColumnWriteTest extends TestCase
{
    public function testMessageNamesTheColumnAndTheTable(): void
    {
        self::assertSame("The value specified for generated column 'g' in table 't' is not allowed.", (new GeneratedColumnWrite(new Name('g'), new Name('t')))->message());
    }
}

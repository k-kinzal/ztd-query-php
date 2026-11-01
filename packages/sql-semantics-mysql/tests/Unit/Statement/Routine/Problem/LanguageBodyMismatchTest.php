<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\LanguageBodyMismatch;

#[CoversClass(LanguageBodyMismatch::class)]
#[Small]
final class LanguageBodyMismatchTest extends TestCase
{
    public function testMessageDescribesTheLanguageRelationship(): void
    {
        self::assertSame('The routine language and body representation disagree.', (new LanguageBodyMismatch())->message());
    }
}

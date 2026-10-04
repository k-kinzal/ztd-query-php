<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\NotComposite;

#[CoversClass(NotComposite::class)]
#[Small]
final class NotCompositeTest extends TestCase
{
    public function testMessageDescribesTheProblem(): void
    {
        self::assertSame('Column notation .a applied to type integer, which is not a composite type.', (new NotComposite('a', 'integer'))->message());
    }
}

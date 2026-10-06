<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\AmbiguousRelation;

#[CoversClass(AmbiguousRelation::class)]
#[Small]
final class AmbiguousRelationTest extends TestCase
{
    public function testMessageDescribesTheProblem(): void
    {
        self::assertSame('Table reference t is ambiguous.', (new AmbiguousRelation('t'))->message());
    }
}

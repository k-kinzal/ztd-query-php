<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\TypeConflict;

#[CoversClass(TypeConflict::class)]
#[Small]
final class TypeConflictTest extends TestCase
{
    public function testMessageDescribesTheProblem(): void
    {
        self::assertSame('CASE types integer and boolean cannot be matched.', (new TypeConflict('CASE', 'integer', 'boolean'))->message());
    }
}

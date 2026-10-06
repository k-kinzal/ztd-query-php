<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Descriptor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\ModifierProblem;

#[CoversClass(ModifierProblem::class)]
#[Small]
final class ModifierProblemTest extends TestCase
{
    public function testMessageNamesTheType(): void
    {
        self::assertSame('Invalid type modifier for type text.', (new ModifierProblem('text'))->message());
    }
}

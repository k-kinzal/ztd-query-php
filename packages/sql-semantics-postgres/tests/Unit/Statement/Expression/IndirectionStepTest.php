<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\FieldSelection;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\IndirectionStep;

#[CoversClass(IndirectionStep::class)]
#[Small]
final class IndirectionStepTest extends TestCase
{
    public function testFieldIsOfferedByAFieldSelection(): void
    {
        self::assertContains(IndirectionStep::class, class_implements(FieldSelection::class));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\EstimateKind;

#[CoversClass(EstimateKind::class)]
#[Small]
final class EstimateKindTest extends TestCase
{
    public function testCasesSpellTheKeyword(): void
    {
        self::assertSame(['COST', 'ROWS'], [EstimateKind::Cost->value, EstimateKind::Rows->value]);
    }
}

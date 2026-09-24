<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Conditional;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Scalar\Conditional\ExtremumKind;

#[CoversClass(ExtremumKind::class)]
final class ExtremumKindTest extends TestCase
{
    public function testRepresentsBothComparisonDirections(): void
    {
        self::assertSame(['GREATEST', 'LEAST'], array_column(ExtremumKind::cases(), 'value'));
    }

    public function testResolvesADirectionFromItsKeyword(): void
    {
        self::assertSame(ExtremumKind::Least, ExtremumKind::from('LEAST'));
    }

    #[TestWith(['MAX'])]
    #[TestWith(['least'])]
    public function testLeavesOtherKeywordsUnclassified(string $keyword): void
    {
        self::assertNull(ExtremumKind::tryFrom($keyword));
    }
}

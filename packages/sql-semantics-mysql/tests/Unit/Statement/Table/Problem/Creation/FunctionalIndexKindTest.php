<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Problem\Creation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\FunctionalIndexKind;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(FunctionalIndexKind::class)]
#[Medium]
final class FunctionalIndexKindTest extends TestCase
{
    public function testMessageRetainsTheKindAndOperand(): void
    {
        $part = new M\Table\Key\ExpressionPart(new M\Literal\NumberLiteral('1'));
        $problem = new FunctionalIndexKind(M\Table\Key\IndexKind::FullText, $part);
        self::assertSame($part, $problem->owner);
        self::assertSame('A FULLTEXT index cannot have a functional key part.', $problem->message());
    }

    public function testOrdinaryKindsCannotClaimAnIncompatibleFunctionalRole(): void
    {
        $this->expectExceptionMessage('A functional-key kind condition belongs to a primary, full-text or spatial index.');
        new FunctionalIndexKind(M\Table\Key\IndexKind::Index, new M\Table\Key\ExpressionPart(new M\Literal\NumberLiteral('1')));
    }

    public function testColumnPartsCannotClaimAFunctionalRole(): void
    {
        $this->expectExceptionMessage('A functional-key kind condition retains an expression operand.');
        new FunctionalIndexKind(M\Table\Key\IndexKind::Spatial, new M\Table\Key\ColumnPart(new Name('a')));
    }
}

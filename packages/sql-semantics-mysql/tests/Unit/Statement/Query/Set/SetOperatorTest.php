<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator;

#[CoversClass(SetOperator::class)]
#[Small]
final class SetOperatorTest extends TestCase
{
    public function testCasesSpellTheOperators(): void
    {
        self::assertSame(['UNION', 'EXCEPT', 'INTERSECT'], array_column(SetOperator::cases(), 'value'));
    }

    public function testTighterTellsThatOnlyIntersectBindsMoreTightly(): void
    {
        self::assertTrue(SetOperator::Intersect->tighter(SetOperator::Union));
        self::assertTrue(SetOperator::Intersect->tighter(SetOperator::Except));
        self::assertFalse(SetOperator::Intersect->tighter(SetOperator::Intersect));
        self::assertFalse(SetOperator::Union->tighter(SetOperator::Except));
        self::assertFalse(SetOperator::Except->tighter(SetOperator::Intersect));
    }
}

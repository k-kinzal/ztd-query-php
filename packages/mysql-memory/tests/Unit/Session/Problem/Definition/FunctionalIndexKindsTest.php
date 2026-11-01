<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Problem\Definition;

use MySqlMemory\Session\Problem\Definition\FunctionalIndexKinds;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(FunctionalIndexKinds::class)]
#[Medium]
final class FunctionalIndexKindsTest extends TestCase
{
    public function testErrorUsesTheBoundIndexKind(): void
    {
        $part = new M\Table\Key\ExpressionPart(new M\Literal\NumberLiteral('1'));
        $error = (new FunctionalIndexKinds())->error(new M\Table\Problem\Creation\FunctionalIndexKind(M\Table\Key\IndexKind::FullText, $part));
        self::assertSame(3759, $error->getCode());
    }

    public function testCheckPreservesAnEarlierColumnTypeFailure(): void
    {
        $part = new M\Table\Key\ExpressionPart(new M\Literal\NumberLiteral('1'));
        $index = new M\Table\Key\IndexDefinition(M\Table\Key\IndexKind::FullText, [new M\Table\Key\ColumnPart(new Name('a')), $part]);
        $problem = new M\Table\Problem\Creation\FunctionalIndexKind(M\Table\Key\IndexKind::FullText, $part);
        $this->expectExceptionCode(1283);
        $this->expectExceptionMessage("Column 'a' cannot be part of FULLTEXT index");
        (new FunctionalIndexKinds())->check($problem, $index, ['a' => \MySqlMemory\Typing\Domain::integer()]);
    }
}

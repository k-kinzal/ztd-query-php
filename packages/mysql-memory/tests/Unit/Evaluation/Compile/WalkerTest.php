<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile;

use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;

#[CoversClass(Walker::class)]
#[Small]
final class WalkerTest extends TestCase
{
    public function testFindFindsTheNodesOfAClassInWrittenOrder(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('SELECT 1 + (SELECT 2), 3 FROM DUAL WHERE 4 IN (SELECT 5)')->statement;

        self::assertSame(['1', '2', '3', '4', '5'], array_map(static fn (NumberLiteral $literal): string => $literal->text, (new Walker())->find($statement, NumberLiteral::class)));
    }

    public function testFindDoesNotDescendIntoSubqueriesWhenAsked(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('SELECT 1 + (SELECT 2), 3 FROM DUAL WHERE 4 IN (SELECT 5)')->statement;

        self::assertSame(['1', '3', '4'], array_map(static fn (NumberLiteral $literal): string => $literal->text, (new Walker())->find($statement, NumberLiteral::class, false)));
    }

    public function testFindIncludesTheRootNode(): void
    {
        $root = new Grouped(new Grouped(new NumberLiteral('1')));

        self::assertSame([$root, $root->operand], (new Walker())->find($root, Grouped::class));
    }

    public function testFindAnswersNothingForAnObjectThatIsNotANode(): void
    {
        self::assertSame([], (new Walker())->find(new Instance(), NumberLiteral::class));
    }

    public function testVisitCollectsTheMatchingNodesOfListProperties(): void
    {
        $row = new Row([new NumberLiteral('1'), new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('2'), new NumberLiteral('3'))]);
        $found = [];
        (new Walker())->visit($row, NumberLiteral::class, true, $found);

        self::assertSame(['1', '2', '3'], array_map(static fn (NumberLiteral $literal): string => $literal->text, $found));
    }
}

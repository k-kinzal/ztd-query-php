<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Window;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Query\Window\Names;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Names::class)]
#[Small]
final class NamesTest extends TestCase
{
    public function testProblemDistinguishesFreeExpressionsFromClosedReadStatements(): void
    {
        $call = new M\Call\Window\WindowFunction(M\Call\Window\WindowFunctionKind::RowNumber, [], new Name('w'));
        $query = new M\Query\Select([], [new M\Query\SelectExpression($call)]);
        self::assertSame(M\Query\Problem\MisuseRule::UnknownWindow, (new Names())->problem($query)?->rule);
    }

    public function testProblemDoesNotBorrowNamesFromAnInnerQueryBlock(): void
    {
        $call = new M\Call\Window\WindowFunction(M\Call\Window\WindowFunctionKind::RowNumber, [], new Name('w'));
        $inner = new M\Query\Select([], [new M\Query\SelectExpression($call)], windows: [new M\Query\Clause\WindowDefinition(new Name('w'), new M\Call\Window\WindowSpec())]);
        $outer = new M\Query\Select([], [new M\Query\SelectExpression(new M\Expression\Subquery\ScalarSubquery($inner)), new M\Query\SelectExpression(new M\Call\Window\WindowFunction(M\Call\Window\WindowFunctionKind::RowNumber, [], new Name('w')))]);
        self::assertSame(M\Query\Problem\MisuseRule::UnknownWindow, (new Names())->problem($outer)?->rule);
    }

    public function testProblemAcceptsForwardReferencesAndRejectsDuplicateNamesWithoutCase(): void
    {
        $number = new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'));
        $one = new M\Query\Clause\WindowDefinition(new Name('w'), new M\Call\Window\WindowSpec(base: new Name('v')));
        $two = new M\Query\Clause\WindowDefinition(new Name('v'), new M\Call\Window\WindowSpec());
        $query = new M\Query\Select([], [$number], windows: [$one, $two]);
        self::assertNull((new Names())->problem($query));
        self::assertSame($query, (new M\QueryRoot($query))->query);
        $duplicate = new M\Query\Select([], [$number], windows: [$one, $two, new M\Query\Clause\WindowDefinition(new Name('W'), new M\Call\Window\WindowSpec())]);
        self::assertSame(M\Query\Problem\MisuseRule::DuplicateWindow, (new Names())->problem($duplicate)?->rule);
    }

    public function testCyclicRejectsAnUnusedCycleButKeepsTheCompleteDeferredTemplate(): void
    {
        $one = new M\Query\Clause\WindowDefinition(new Name('w'), new M\Call\Window\WindowSpec(base: new Name('v')));
        $two = new M\Query\Clause\WindowDefinition(new Name('v'), new M\Call\Window\WindowSpec(base: new Name('W')));
        self::assertTrue((new Names())->cyclic(['w' => $one, 'v' => $two]));
        self::assertFalse((new Names())->cyclic(['w' => $one]));
        $query = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))], windows: [$one, $two]);
        $retained = new M\Routine\Program\Selection\DeferredQuery($query);
        self::assertSame($query, $retained->query);
        $problem = $retained->problem();
        self::assertInstanceOf(M\Query\Problem\Misuse::class, $problem);
        self::assertSame(M\Query\Problem\MisuseRule::WindowCircularity, $problem->rule);
    }
}

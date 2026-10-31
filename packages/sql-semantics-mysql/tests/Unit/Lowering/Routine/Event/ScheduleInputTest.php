<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Platform\MySql\Lowering\Routine\Event\ScheduleInput;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\Problem\RefusedOperand;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(ScheduleInput::class)]
#[Medium]
final class ScheduleInputTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerDependencies(): iterable
    {
        yield 'table-free subquery' => ['ALTER EVENT e ON SCHEDULE AT (SELECT 1)', false];
        yield 'later table after row operand' => ['ALTER EVENT e ON SCHEDULE AT ((1,2) + (SELECT 1 FROM absent))', true];
        yield 'unused definition' => ['ALTER EVENT e ON SCHEDULE AT ((1,2) + (WITH c AS (SELECT 1 FROM absent) SELECT 1))', false];
        yield 'referenced constant definition' => ['ALTER EVENT e ON SCHEDULE AT ((1,2) + (WITH c AS (SELECT 1) SELECT * FROM c))', true];
        yield 'later schedule bound' => ['ALTER EVENT e ON SCHEDULE EVERY (1,2) DAY STARTS (TABLE absent)', true];
    }

    #[DataProvider('providerDependencies')]
    public function testReadsTableInspectsAllActiveScheduleBodies(string $sql, bool $expected): void
    {
        self::assertSame($expected, (new ScheduleInput())->readsTable((new MySqlParser('mysql-8.4.7'))->parse($sql)));
    }

    public function testOperandsRetainsTheIntervalAndBothBounds(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
        $tree = $semantics->parser()->parse('ALTER EVENT d.e ON SCHEDULE EVERY (1,2) DAY STARTS (SELECT 1 FROM t) ENDS 9');
        $lowering = new \SqlSemantics\Platform\MySql\Lowering\Lowering((new \SqlSemantics\Platform\MySql\Platform())->productions($semantics->profile()), new \SqlSemantics\Lowering\Leaves(), $semantics->profile());
        $operands = (new ScheduleInput())->operands($tree->find('ev_schedule_time')[0], $lowering);
        self::assertCount(3, $operands);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand::class, $operands[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery::class, $operands[1]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral::class, $operands[2]);
    }

    public function testWrapPreservesRecursiveReferenceConstraintsAndTheEventHeader(): void
    {
        $tree = (new MySqlParser('mysql-8.4.7'))->parse('ALTER EVENT d.e ON SCHEDULE AT (WITH RECURSIVE c AS (SELECT 1 UNION ALL SELECT 1 FROM c a,c b) SELECT * FROM c)');
        $problem = new \SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse(\SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule::RecursiveReferencePosition, new Name('c'));
        $name = new QualifiedName(new Name('e'), new Name('d'));
        $wrapped = (new ScheduleInput())->wrap(new RejectedInput($problem, 100, false), $tree, $name, null);
        self::assertInstanceOf(RefusedOperand::class, $wrapped->problem);
        self::assertSame($problem, $wrapped->problem->problem);
        self::assertSame($name, $wrapped->problem->name);
    }

    public function testWrapKeepsTheHeaderAndEarlierDependencyCondition(): void
    {
        $tree = (new MySqlParser('mysql-8.4.7'))->parse('ALTER EVENT e ON SCHEDULE AT ((1,2) + (SELECT 1 FROM absent))');
        $failure = new RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Routine\Event\Problem\OperandWidth(new OperandColumns(1, 2), \SqlSemantics\Platform\MySql\Statement\Routine\Event\Clause::At, true), 10, false);
        $name = new QualifiedName(new Name('e'), new Name('d'));
        $wrapped = (new ScheduleInput())->wrap($failure, $tree, $name, null);

        self::assertInstanceOf(RefusedOperand::class, $wrapped->problem);
        self::assertSame($name, $wrapped->problem->name);
        self::assertInstanceOf(NotSupportedYet::class, $wrapped->problem->problem);
        self::assertSame([10, false], [$wrapped->end, $wrapped->aborts]);
    }

    public function testWrapPreservesAnEarlierNonWidthRefusal(): void
    {
        $tree = (new MySqlParser('mysql-8.4.7'))->parse('ALTER EVENT e ON SCHEDULE AT (SELECT 1 FROM absent)');
        $failure = new RejectedInput(new NotSupportedYet('AT LOCAL'), 10, false);

        self::assertSame($failure, (new ScheduleInput())->wrap($failure, $tree, new QualifiedName(new Name('e')), null));
    }

    public function testWrapKeepsScheduleOwnershipWhenTheWholeOperandIsDeferred(): void
    {
        $tree = (new MySqlParser('mysql-8.4.7'))->parse('ALTER EVENT e ON SCHEDULE EVERY ((1,2) = ALL(TABLE absent)) DAY');
        $operand = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]);
        $failure = new RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedScalar($operand), 10, false);
        $wrapped = (new ScheduleInput())->wrap($failure, $tree, new QualifiedName(new Name('e')), null);
        self::assertInstanceOf(RefusedOperand::class, $wrapped->problem);
        self::assertInstanceOf(NotSupportedYet::class, $wrapped->problem->problem);
        $withoutTable = (new MySqlParser('mysql-8.4.7'))->parse('ALTER EVENT e ON SCHEDULE EVERY (1,2) DAY');
        self::assertSame($failure, (new ScheduleInput())->wrap($failure, $withoutTable, new QualifiedName(new Name('e')), null));
    }
}

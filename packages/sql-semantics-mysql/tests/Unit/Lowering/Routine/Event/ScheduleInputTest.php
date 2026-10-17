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
}

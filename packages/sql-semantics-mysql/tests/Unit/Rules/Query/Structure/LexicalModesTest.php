<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Structure;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Logical;
use SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Not;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Concatenation;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\UserAssignment;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;

#[CoversClass(LexicalModes::class)]
#[Small]
final class LexicalModesTest extends TestCase
{
    /**
     * @return iterable<string, array{object, int}>
     */
    public static function providerRequirements(): iterable
    {
        yield 'literal' => [new NumberLiteral('1'), 0];
        yield 'low NOT' => [new Not(new NumberLiteral('1')), 1];
        yield 'high NOT keyword' => [new Unary(UnaryOperator::Not, new NumberLiteral('1'), true), 2];
        yield 'high NOT symbol' => [new Unary(UnaryOperator::Not, new NumberLiteral('1')), 0];
        yield 'OR keyword' => [new Logical(LogicalOperator::Or, new NumberLiteral('1'), new NumberLiteral('0')), 0];
        yield 'OR pipes' => [new Logical(LogicalOperator::Or, new NumberLiteral('1'), new NumberLiteral('0'), true), 4];
        yield 'AND symbol' => [new Logical(LogicalOperator::And, new NumberLiteral('1'), new NumberLiteral('0'), true), 0];
        yield 'concatenate pipes' => [new Concatenation(new NumberLiteral('1'), new NumberLiteral('0')), 8];
    }

    #[DataProvider('providerRequirements')]
    public function testRequirementDescribesTheLexicalVariant(object $value, int $expected): void
    {
        self::assertSame($expected, (new LexicalModes())->requirement($value));
    }

    /**
     * @return iterable<string, array{Closure(): Node, string}>
     */
    public static function providerConflicts(): iterable
    {
        yield 'statement assignments' => [static fn (): Node => new SetVariables([new UserAssignment(new UserVariable(new Name('x')), new Not(new NumberLiteral('1'))), new UserAssignment(new UserVariable(new Name('y')), new Unary(UnaryOperator::Not, new NumberLiteral('1'), true))]), 'HIGH_NOT_PRECEDENCE'];
        yield 'nested NOT' => [static fn (): Node => new Not(new Unary(UnaryOperator::Not, new NumberLiteral('1'), true)), 'HIGH_NOT_PRECEDENCE'];
        yield 'grouped NOT' => [static fn (): Node => new Unary(UnaryOperator::Not, new Grouped(new Not(new NumberLiteral('1'))), true), 'HIGH_NOT_PRECEDENCE'];
        yield 'sibling items' => [static fn (): Node => new Select([], [new SelectExpression(new Not(new NumberLiteral('1'))), new SelectExpression(new Unary(UnaryOperator::Not, new NumberLiteral('1'), true))]), 'HIGH_NOT_PRECEDENCE'];
        yield 'subquery NOT' => [static fn (): Node => new Not(new ScalarSubquery(new Select([], [new SelectExpression(new Unary(UnaryOperator::Not, new NumberLiteral('1'), true))]))), 'HIGH_NOT_PRECEDENCE'];
        yield 'nested pipes' => [static fn (): Node => new Logical(LogicalOperator::Or, new Concatenation(new NumberLiteral('1'), new NumberLiteral('2')), new NumberLiteral('0'), true), 'PIPES_AS_CONCAT'];
        yield 'grouped pipes' => [static fn (): Node => new Concatenation(new Grouped(new Logical(LogicalOperator::Or, new NumberLiteral('1'), new NumberLiteral('0'), true)), new NumberLiteral('2')), 'PIPES_AS_CONCAT'];
        yield 'sibling pipes' => [static fn (): Node => new Select([], [new SelectExpression(new Concatenation(new NumberLiteral('1'), new NumberLiteral('2'))), new SelectExpression(new Logical(LogicalOperator::Or, new NumberLiteral('1'), new NumberLiteral('0'), true))]), 'PIPES_AS_CONCAT'];
    }

    /**
     * @param Closure(): Node $construct
     */
    #[DataProvider('providerConflicts')]
    public function testCheckRejectsContradictoryModesDuringConstruction(Closure $construct, string $mode): void
    {
        $this->expectExceptionMessage('A structure cannot require both enabled and disabled ' . $mode . '.');

        $construct();
    }

    public function testCheckAllowsIndependentModeRequirements(): void
    {
        (new LexicalModes())->check(1 | 8);
        $query = new Select([], [new SelectExpression(new Not(new NumberLiteral('1'))), new SelectExpression(new Concatenation(new NumberLiteral('1'), new NumberLiteral('2')))]);

        self::assertCount(2, $query->items);
    }
}

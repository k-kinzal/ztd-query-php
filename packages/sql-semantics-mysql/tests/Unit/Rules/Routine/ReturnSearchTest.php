<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Routine\ReturnSearch;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionClass;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\GeneralCondition;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ConditionalBranch;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\IfStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\Leave;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\Loop;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\RepeatLoop;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\SearchedCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\SimpleCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\WhileLoop;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerAction;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerDeclaration;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\VariableDeclaration;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\UserAssignment;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;

#[CoversClass(ReturnSearch::class)]
#[Small]
final class ReturnSearchTest extends TestCase
{
    #[DataProvider('providerFoundFindsANestedReturn')]
    public function testFoundFindsANestedReturn(Node $statement): void
    {
        self::assertTrue((new ReturnSearch())->found($statement));
    }

    /**
     * @return iterable<string, array{Node}>
     */
    public static function providerFoundFindsANestedReturn(): iterable
    {
        $set = new SetVariables([new UserAssignment(new UserVariable(new Name('a')), new NumberLiteral('1'))]);
        $return = new ReturnStatement(new NumberLiteral('1'));

        yield 'a RETURN itself' => [$return];
        yield 'a RETURN in a block' => [new Block([], [$set, $return])];
        yield 'a RETURN in a handler of a block' => [new Block([new HandlerDeclaration(HandlerAction::Continue, [new GeneralCondition(ConditionClass::SqlException)], $return)], [$set])];
        yield 'a RETURN in a branch of IF' => [new IfStatement([new ConditionalBranch(new NumberLiteral('1'), [$set]), new ConditionalBranch(new NumberLiteral('0'), [$return])])];
        yield 'a RETURN in the ELSE of IF' => [new IfStatement([new ConditionalBranch(new NumberLiteral('1'), [$set])], [$return])];
        yield 'a RETURN in a simple CASE' => [new SimpleCase(new NumberLiteral('1'), [new ConditionalBranch(new NumberLiteral('1'), [$return])])];
        yield 'a RETURN in the ELSE of a searched CASE' => [new SearchedCase([new ConditionalBranch(new NumberLiteral('1'), [$set])], [$return])];
        yield 'a RETURN in a LOOP' => [new Loop([$return])];
        yield 'a RETURN in a WHILE loop' => [new WhileLoop(new NumberLiteral('1'), [$return])];
        yield 'a RETURN in a REPEAT loop' => [new RepeatLoop(new NumberLiteral('1'), [$set, $return])];
        yield 'a RETURN deep in nested statements' => [new Block([], [new Loop([new IfStatement([new ConditionalBranch(new NumberLiteral('1'), [new Block([], [$return])])])])])];
    }

    #[DataProvider('providerFoundIsFalseWithoutReturn')]
    public function testFoundIsFalseWithoutReturn(Node $statement): void
    {
        self::assertFalse((new ReturnSearch())->found($statement));
    }

    /**
     * @return iterable<string, array{Node}>
     */
    public static function providerFoundIsFalseWithoutReturn(): iterable
    {
        $set = new SetVariables([new UserAssignment(new UserVariable(new Name('a')), new NumberLiteral('1'))]);

        yield 'an SQL statement' => [$set];
        yield 'an empty block' => [new Block()];
        yield 'a block with a variable declaration' => [new Block([new VariableDeclaration([new Name('v')], new Integral(IntegralKind::Int))], [$set])];
        yield 'a handler without RETURN' => [new Block([new HandlerDeclaration(HandlerAction::Exit, [new GeneralCondition(ConditionClass::NotFound)], $set)])];
        yield 'conditionals and loops without RETURN' => [new Block([], [new IfStatement([new ConditionalBranch(new NumberLiteral('1'), [$set])], [$set]), new WhileLoop(new NumberLiteral('1'), [$set]), new Loop([new Leave(new Name('l'))], new Name('l'))])];
    }

    public function testNestedAnswersTheStatementsAndHandlerStatementsOfABlock(): void
    {
        $set = new SetVariables([new UserAssignment(new UserVariable(new Name('a')), new NumberLiteral('1'))]);
        $return = new ReturnStatement(new NumberLiteral('1'));
        $block = new Block([new HandlerDeclaration(HandlerAction::Continue, [new GeneralCondition(ConditionClass::SqlWarning)], $return)], [$set]);

        self::assertSame([$set, $return], (new ReturnSearch())->nested($block));
    }

    public function testNestedAnswersTheElseStatementsBeforeTheBranchStatements(): void
    {
        $first = new SetVariables([new UserAssignment(new UserVariable(new Name('a')), new NumberLiteral('1'))]);
        $second = new SetVariables([new UserAssignment(new UserVariable(new Name('b')), new NumberLiteral('2'))]);
        $otherwise = new ReturnStatement(new NumberLiteral('3'));
        $branches = [new ConditionalBranch(new NumberLiteral('1'), [$first]), new ConditionalBranch(new NumberLiteral('2'), [$second])];
        $search = new ReturnSearch();

        self::assertSame([$otherwise, $first, $second], $search->nested(new IfStatement($branches, [$otherwise])));
        self::assertSame([$otherwise, $first, $second], $search->nested(new SimpleCase(new NumberLiteral('1'), $branches, [$otherwise])));
        self::assertSame([$first, $second], $search->nested(new SearchedCase($branches)));
    }

    public function testNestedAnswersTheStatementsOfALoop(): void
    {
        $return = new ReturnStatement(new NumberLiteral('1'));
        $leave = new Leave(new Name('l'));
        $search = new ReturnSearch();

        self::assertSame([$leave, $return], $search->nested(new Loop([$leave, $return], new Name('l'))));
        self::assertSame([$return], $search->nested(new WhileLoop(new NumberLiteral('1'), [$return])));
        self::assertSame([$return], $search->nested(new RepeatLoop(new NumberLiteral('1'), [$return])));
    }

    public function testNestedAnswersNothingForAStatementWithoutNestedStatements(): void
    {
        $search = new ReturnSearch();

        self::assertSame([], $search->nested(new ReturnStatement(new NumberLiteral('1'))));
        self::assertSame([], $search->nested(new SetVariables([new UserAssignment(new UserVariable(new Name('a')), new NumberLiteral('1'))])));
        self::assertSame([], $search->nested(new NumberLiteral('1')));
    }
}

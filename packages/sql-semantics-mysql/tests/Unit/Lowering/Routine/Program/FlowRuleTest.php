<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Routine\Program\FlowRule;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ConditionalBranch;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\IfStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\Iterate;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\Leave;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\Loop;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\RepeatLoop;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\SearchedCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\SimpleCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\WhileLoop;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\NameAssignment;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(FlowRule::class)]
#[Medium]
final class FlowRuleTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerConditionalLowersTheBranchesAndTheElse(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 5.7' => ['mysql-5.7.44'];
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerConditionalLowersTheBranchesAndTheElse')]
    public function testConditionalLowersTheBranchesAndTheElse(string $release): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze('create procedure p(x int) if x = 1 then set @a = 1; set @a = 2; elseif x = 2 then set @a = 3; elseif x = 3 then set @a = 4; else set @a = 5; end if');
        $statement = $operation->statement;
        self::assertInstanceOf(CreateProcedure::class, $statement);
        $conditional = $statement->body;
        self::assertInstanceOf(IfStatement::class, $conditional);

        self::assertCount(3, $conditional->branches);
        self::assertEquals(new Comparison(ComparisonOperator::Equal, new ColumnUse(new Name('x')), new NumberLiteral('1')), $conditional->branches[0]->condition);
        self::assertEquals(new Comparison(ComparisonOperator::Equal, new ColumnUse(new Name('x')), new NumberLiteral('3')), $conditional->branches[2]->condition);
        self::assertCount(2, $conditional->branches[0]->statements);
        self::assertCount(1, $conditional->branches[1]->statements);
        self::assertCount(1, $conditional->otherwise);
        self::assertContainsOnlyInstancesOf(SetVariables::class, $conditional->otherwise);
        self::assertSame('CREATE PROCEDURE p(x INT) IF x = 1 THEN SET @a = 1; SET @a = 2; ELSEIF x = 2 THEN SET @a = 3; ELSEIF x = 3 THEN SET @a = 4; ELSE SET @a = 5; END IF', $operation->toString());
        self::assertSame([], $operation->facts->diagnostics);
    }

    /**
     * @return iterable<string, array{string, string, int, string}>
     */
    public static function providerConditionalLowersAnIfWithoutElse(): iterable
    {
        yield 'one branch in 5.6' => ['mysql-5.6.51', 'if x then leave b; end if', 1, 'IF x THEN LEAVE b; END IF'];
        yield 'two branches in 8.0' => ['mysql-8.0.44', 'if x then leave b; elseif x > 1 then leave b; end if', 2, 'IF x THEN LEAVE b; ELSEIF x > 1 THEN LEAVE b; END IF'];
        yield 'two branches in 9.1' => ['mysql-9.1.0', 'if x then leave b; elseif x > 1 then leave b; end if', 2, 'IF x THEN LEAVE b; ELSEIF x > 1 THEN LEAVE b; END IF'];
    }

    #[DataProvider('providerConditionalLowersAnIfWithoutElse')]
    public function testConditionalLowersAnIfWithoutElse(string $release, string $sql, int $branches, string $rendering): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze('create procedure p(x int) b: begin ' . $sql . '; end');
        $statement = $operation->statement;
        self::assertInstanceOf(CreateProcedure::class, $statement);
        self::assertInstanceOf(Block::class, $statement->body);
        $conditional = $statement->body->statements[0];
        self::assertInstanceOf(IfStatement::class, $conditional);

        self::assertCount($branches, $conditional->branches);
        self::assertEquals(new ConditionalBranch(new ColumnUse(new Name('x')), [new Leave(new Name('b'))]), $conditional->branches[0]);
        self::assertSame([], $conditional->otherwise);
        self::assertSame('CREATE PROCEDURE p(x INT) b: BEGIN ' . $rendering . '; END', $operation->toString());
    }

    public function testConditionalRefusesAnotherProduction(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rule = new FlowRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: rule: IF x');

        $rule->conditional(new Form(new Node('rule', 0, []), 'rule: IF x'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerChoiceLowersASimpleCase(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 5.7' => ['mysql-5.7.44'];
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerChoiceLowersASimpleCase')]
    public function testChoiceLowersASimpleCase(string $release): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze('create procedure p(x int) case x when 1 then set x = 2; when 2 then set x = 3; set x = 4; else set x = 5; end case');
        $statement = $operation->statement;
        self::assertInstanceOf(CreateProcedure::class, $statement);

        self::assertEquals(new SimpleCase(new ColumnUse(new Name('x')), [
            new ConditionalBranch(new NumberLiteral('1'), [new SetVariables([new NameAssignment(new Name('x'), new NumberLiteral('2'))])]),
            new ConditionalBranch(new NumberLiteral('2'), [new SetVariables([new NameAssignment(new Name('x'), new NumberLiteral('3'))]), new SetVariables([new NameAssignment(new Name('x'), new NumberLiteral('4'))])]),
        ], [new SetVariables([new NameAssignment(new Name('x'), new NumberLiteral('5'))])]), $statement->body);
        self::assertSame('CREATE PROCEDURE p(x INT) CASE x WHEN 1 THEN SET x = 2; WHEN 2 THEN SET x = 3; SET x = 4; ELSE SET x = 5; END CASE', $operation->toString());
        self::assertSame([], $operation->facts->diagnostics);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerChoiceLowersASearchedCase(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 5.7' => ['mysql-5.7.44'];
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerChoiceLowersASearchedCase')]
    public function testChoiceLowersASearchedCase(string $release): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze('create procedure p(x int) case when x > 1 then set x = 0; end case');
        $statement = $operation->statement;
        self::assertInstanceOf(CreateProcedure::class, $statement);

        self::assertEquals(new SearchedCase([
            new ConditionalBranch(new Comparison(ComparisonOperator::Greater, new ColumnUse(new Name('x')), new NumberLiteral('1')), [new SetVariables([new NameAssignment(new Name('x'), new NumberLiteral('0'))])]),
        ]), $statement->body);
        self::assertSame('CREATE PROCEDURE p(x INT) CASE WHEN x > 1 THEN SET x = 0; END CASE', $operation->toString());
    }

    /**
     * @return iterable<string, array{string, string, Loop|WhileLoop|RepeatLoop, string}>
     */
    public static function providerLabeledLowersALabeledLoop(): iterable
    {
        yield 'a loop with both labels in 5.6' => ['mysql-5.6.51', 'l: loop leave l; end loop l', new Loop([new Leave(new Name('l'))], new Name('l'), new Name('l')), 'l: LOOP LEAVE l; END LOOP l'];
        yield 'a while loop with both labels in 5.7' => ['mysql-5.7.44', 'l: while 1 do iterate l; end while l', new WhileLoop(new NumberLiteral('1'), [new Iterate(new Name('l'))], new Name('l'), new Name('l')), 'l: WHILE 1 DO ITERATE l; END WHILE l'];
        yield 'a repeat loop with a label in 8.0' => ['mysql-8.0.44', 'l: repeat leave l; until 1 end repeat', new RepeatLoop(new NumberLiteral('1'), [new Leave(new Name('l'))], new Name('l')), 'l: REPEAT LEAVE l; UNTIL 1 END REPEAT'];
        yield 'a while loop with a label in 9.1' => ['mysql-9.1.0', 'l: while 1 do leave l; end while', new WhileLoop(new NumberLiteral('1'), [new Leave(new Name('l'))], new Name('l')), 'l: WHILE 1 DO LEAVE l; END WHILE'];
    }

    #[DataProvider('providerLabeledLowersALabeledLoop')]
    public function testLabeledLowersALabeledLoop(string $release, string $sql, Loop|WhileLoop|RepeatLoop $expected, string $rendering): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze('create procedure p() ' . $sql);
        self::assertInstanceOf(CreateProcedure::class, $operation->statement);

        self::assertEquals($expected, $operation->statement->body);
        self::assertSame('CREATE PROCEDURE p() ' . $rendering, $operation->toString());
        self::assertSame([], $operation->facts->diagnostics);
    }

    /**
     * @return iterable<string, array{string, string, Name|null, string}>
     */
    public static function providerEndLabelLowersTheLabelAfterEnd(): iterable
    {
        yield 'written in 5.6' => ['mysql-5.6.51', 'l: repeat leave l; until 1 end repeat l', new Name('l'), 'l: REPEAT LEAVE l; UNTIL 1 END REPEAT l'];
        yield 'absent in 5.7' => ['mysql-5.7.44', 'l: repeat leave l; until 1 end repeat', null, 'l: REPEAT LEAVE l; UNTIL 1 END REPEAT'];
        yield 'written in 9.1' => ['mysql-9.1.0', 'l: repeat leave l; until 1 end repeat l', new Name('l'), 'l: REPEAT LEAVE l; UNTIL 1 END REPEAT l'];
    }

    #[DataProvider('providerEndLabelLowersTheLabelAfterEnd')]
    public function testEndLabelLowersTheLabelAfterEnd(string $release, string $sql, ?Name $endLabel, string $rendering): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze('create procedure p() ' . $sql);
        self::assertInstanceOf(CreateProcedure::class, $operation->statement);
        $loop = $operation->statement->body;
        self::assertInstanceOf(RepeatLoop::class, $loop);

        self::assertEquals($endLabel, $loop->endLabel);
        self::assertSame('CREATE PROCEDURE p() ' . $rendering, $operation->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerLoopLowersLoopWhileAndRepeat(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 5.7' => ['mysql-5.7.44'];
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerLoopLowersLoopWhileAndRepeat')]
    public function testLoopLowersLoopWhileAndRepeat(string $release): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze('create procedure p(x int) begin while x > 0 do set x = x - 1; end while; repeat set x = x + 1; until x > 9 end repeat; loop set x = 0; end loop; end');
        $statement = $operation->statement;
        self::assertInstanceOf(CreateProcedure::class, $statement);
        self::assertInstanceOf(Block::class, $statement->body);
        $x = new ColumnUse(new Name('x'));

        self::assertEquals([
            new WhileLoop(new Comparison(ComparisonOperator::Greater, $x, new NumberLiteral('0')), [new SetVariables([new NameAssignment(new Name('x'), new Arithmetic(ArithmeticOperator::Minus, $x, new NumberLiteral('1')))])]),
            new RepeatLoop(new Comparison(ComparisonOperator::Greater, $x, new NumberLiteral('9')), [new SetVariables([new NameAssignment(new Name('x'), new Arithmetic(ArithmeticOperator::Plus, $x, new NumberLiteral('1')))])]),
            new Loop([new SetVariables([new NameAssignment(new Name('x'), new NumberLiteral('0'))])]),
        ], $statement->body->statements);
        self::assertSame('CREATE PROCEDURE p(x INT) BEGIN WHILE x > 0 DO SET x = x - 1; END WHILE; REPEAT SET x = x + 1; UNTIL x > 9 END REPEAT; LOOP SET x = 0; END LOOP; END', $operation->toString());
        self::assertSame([], $operation->facts->diagnostics);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerJumpLowersReturnLeaveAndIterate(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 5.7' => ['mysql-5.7.44'];
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerJumpLowersReturnLeaveAndIterate')]
    public function testJumpLowersReturnLeaveAndIterate(string $release): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze('create function f(x int) returns int begin l: loop if x > 0 then leave l; end if; iterate l; end loop; return x + 1; end');
        $statement = $operation->statement;
        self::assertInstanceOf(CreateFunction::class, $statement);
        self::assertInstanceOf(Block::class, $statement->body);
        $x = new ColumnUse(new Name('x'));

        self::assertEquals([
            new Loop([new IfStatement([new ConditionalBranch(new Comparison(ComparisonOperator::Greater, $x, new NumberLiteral('0')), [new Leave(new Name('l'))])]), new Iterate(new Name('l'))], new Name('l')),
            new ReturnStatement(new Arithmetic(ArithmeticOperator::Plus, $x, new NumberLiteral('1'))),
        ], $statement->body->statements);
        self::assertSame('CREATE FUNCTION f(x INT) RETURNS INT BEGIN l: LOOP IF x > 0 THEN LEAVE l; END IF; ITERATE l; END LOOP; RETURN x + 1; END', $operation->toString());
        self::assertSame([], $operation->facts->diagnostics);
    }
}

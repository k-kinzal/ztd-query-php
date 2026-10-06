<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Routine\FlowFacts;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\IfStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\WhileLoop;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

#[CoversClass(FlowFacts::class)]
#[Medium]
final class FlowFactsTest extends TestCase
{
    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerBranchesDerivesEveryConditionAndStatement')]
    public function testBranchesDerivesEveryConditionAndStatement(string $release, string $sql, array $messages): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze($sql);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function providerBranchesDerivesEveryConditionAndStatement(): iterable
    {
        yield 'IF with ELSEIF and ELSE in 5.6' => ['mysql-5.6.51', 'CREATE PROCEDURE p(x INT) IF x THEN SELECT x; ELSEIF y THEN SELECT 1; ELSE SELECT z; END IF', ['Column y does not exist.', 'Column z does not exist.']];
        yield 'a simple CASE in 8.0' => ['mysql-8.0.44', 'CREATE PROCEDURE p(x INT) CASE x WHEN 1 THEN SELECT 1; WHEN y THEN SELECT 2; ELSE SELECT 3; END CASE', ['Column y does not exist.']];
        yield 'a searched CASE in 9.1' => ['mysql-9.1.0', 'CREATE PROCEDURE p(x INT) CASE WHEN x = 1 THEN SELECT 1; ELSE LEAVE l; END CASE', ['LEAVE with no matching label: l']];
        yield 'variables in every branch' => ['mysql-9.1.0', 'CREATE PROCEDURE p() BEGIN DECLARE x INT; IF x > 0 THEN SET x = 1; ELSEIF x < 0 THEN SET x = 2; ELSE SELECT x; END IF; END', []];
    }

    public function testBranchesResolvesAVariableInTheCondition(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN DECLARE x INT; IF x > 0 THEN SELECT 1; END IF; END');
        $create = $operation->statement;
        self::assertInstanceOf(CreateProcedure::class, $create);
        $block = $create->body;
        self::assertInstanceOf(Block::class, $block);
        $if = $block->statements[0];
        self::assertInstanceOf(IfStatement::class, $if);
        $condition = $if->branches[0]->condition;
        self::assertInstanceOf(Comparison::class, $condition);
        $resolution = $operation->facts->scalar($condition->left)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);

        self::assertSame([], $operation->facts->diagnostics);
        self::assertSame($block->declarations[0], $resolution->relation);
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerLoopDerivesTheLabelsTheConditionAndTheStatements')]
    public function testLoopDerivesTheLabelsTheConditionAndTheStatements(string $release, string $sql, array $messages): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze($sql);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function providerLoopDerivesTheLabelsTheConditionAndTheStatements(): iterable
    {
        yield 'WHILE with an unknown name in 5.7' => ['mysql-5.7.44', 'CREATE PROCEDURE p(x INT) w: WHILE x > y DO ITERATE w; END WHILE w', ['Column y does not exist.']];
        yield 'REPEAT with its label in 8.0' => ['mysql-8.0.44', 'CREATE PROCEDURE p(x INT) r: REPEAT SET x = x - 1; UNTIL x < 0 END REPEAT r', []];
        yield 'REPEAT with another end label' => ['mysql-9.1.0', 'CREATE PROCEDURE p(x INT) r: REPEAT SET x = x - 1; UNTIL q END REPEAT s', ['End-label s without match', 'Column q does not exist.']];
        yield 'LOOP with an unknown name' => ['mysql-9.1.0', 'CREATE PROCEDURE p() l: LOOP SELECT z; LEAVE l; END LOOP', ['Column z does not exist.']];
        yield 'a redefined loop label' => ['mysql-9.1.0', 'CREATE PROCEDURE p() a: BEGIN a: WHILE 1 DO LEAVE a; END WHILE; END', ['Redefining label a']];
    }

    public function testLoopResolvesAVariableInTheCondition(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN DECLARE x INT DEFAULT 3; WHILE x > 0 DO SET x = x - 1; END WHILE; END');
        $create = $operation->statement;
        self::assertInstanceOf(CreateProcedure::class, $create);
        $block = $create->body;
        self::assertInstanceOf(Block::class, $block);
        $loop = $block->statements[0];
        self::assertInstanceOf(WhileLoop::class, $loop);
        $condition = $loop->condition;
        self::assertInstanceOf(Comparison::class, $condition);
        $resolution = $operation->facts->scalar($condition->left)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);

        self::assertSame([], $operation->facts->diagnostics);
        self::assertSame($block->declarations[0], $resolution->relation);
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerJumpChecksTheLabel')]
    public function testJumpChecksTheLabel(string $sql, array $messages): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze($sql);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerJumpChecksTheLabel(): iterable
    {
        yield 'ITERATE of an unknown label' => ['CREATE PROCEDURE p() l: LOOP ITERATE m; END LOOP', ['ITERATE with no matching label: m']];
        yield 'ITERATE of a block label' => ['CREATE PROCEDURE p() b: BEGIN l: LOOP ITERATE b; END LOOP; END', ['ITERATE with no matching label: b']];
        yield 'LEAVE of a closed loop' => ['CREATE PROCEDURE p() BEGIN l: LOOP LEAVE l; END LOOP; LEAVE l; END', ['LEAVE with no matching label: l']];
        yield 'LEAVE of an outer label from a handler' => ['CREATE PROCEDURE p() l: LOOP BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION LEAVE l; END; END LOOP', ['LEAVE with no matching label: l']];
        yield 'LEAVE and ITERATE in another case' => ['CREATE PROCEDURE p() b: BEGIN l: LOOP LEAVE B; ITERATE L; END LOOP; END', []];
        yield 'LEAVE of an outer block' => ['CREATE PROCEDURE p() a: BEGIN b: BEGIN LEAVE a; END b; END a', []];
        yield 'a label inside a handler' => ['CREATE PROCEDURE p() l: BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION l: BEGIN LEAVE l; END; END', []];
    }

    /**
     * @param list<string> $messages
     */
    #[DataProvider('providerReturnedReportsAReturnOutsideAFunction')]
    public function testReturnedReportsAReturnOutsideAFunction(string $sql, array $messages): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze($sql, [$semantics->analyze('CREATE TABLE t (a INT)')]);

        self::assertSame($messages, array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReturnedReportsAReturnOutsideAFunction(): iterable
    {
        yield 'a function' => ['CREATE FUNCTION f(x INT) RETURNS INT BEGIN IF x THEN RETURN 1; END IF; RETURN x; END', []];
        yield 'an unknown name in a function' => ['CREATE FUNCTION f(x INT) RETURNS INT RETURN y', ['Column y does not exist.']];
        yield 'a procedure' => ['CREATE PROCEDURE p() BEGIN RETURN 1; END', ['RETURN is only allowed in a FUNCTION']];
        yield 'a trigger' => ['CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW RETURN NEW.a', ['RETURN is only allowed in a FUNCTION']];
        yield 'an event' => ['CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO RETURN 1', ['RETURN is only allowed in a FUNCTION']];
    }

    public function testReturnedResolvesTheValueToALocalVariable(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE FUNCTION f() RETURNS INT BEGIN DECLARE x INT DEFAULT 2; RETURN x; END');
        $create = $operation->statement;
        self::assertInstanceOf(CreateFunction::class, $create);
        $block = $create->body;
        self::assertInstanceOf(Block::class, $block);
        $return = $block->statements[0];
        self::assertInstanceOf(ReturnStatement::class, $return);
        $resolution = $operation->facts->scalar($return->value)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);

        self::assertSame([], $operation->facts->diagnostics);
        self::assertSame($block->declarations[0], $resolution->relation);
    }
}

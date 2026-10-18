<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Lowering\Productions;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Routine\Program\InputPrefix;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionName;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(InputPrefix::class)]
#[Medium]
final class InputPrefixTest extends TestCase
{
    public function testImmediateLeavesFunctionReturnsAndDeferredResultSetsToTheirDeclaration(): void
    {
        $rule = new InputPrefix(new Lowering(Productions::load(__DIR__ . '/../../../../../resources/productions/mysql-8.4.7.php'), new Leaves(), new LanguageProfile(GrammarRelease::MySql847)));
        $body = new Block(statements: [new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]);

        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule::ReturnOutsideFunction, $rule->immediate($body, \SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind::Procedure)?->rule);
        self::assertNull($rule->immediate($body, \SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind::Function));
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function providerPrefixes(): iterable
    {
        yield 'unbound condition' => ['SIGNAL missing_condition;', 'Undefined CONDITION: missing_condition'];
        yield 'undefined cursor open' => ['OPEN c;', 'Undefined CURSOR: c'];
        yield 'undefined cursor close' => ['CLOSE c;', 'Undefined CURSOR: c'];
        yield 'undefined cursor fetch' => ['DECLARE x INT; FETCH c INTO x;', 'Undefined CURSOR: c'];
        yield 'declared cursor' => ['DECLARE c CURSOR FOR SELECT 1; OPEN c;', null];
        yield 'outer cursor' => ['DECLARE c CURSOR FOR SELECT 1; BEGIN CLOSE c; END;', null];
        yield 'closed cursor scope' => ['BEGIN DECLARE c CURSOR FOR SELECT 1; END; OPEN c;', 'Undefined CURSOR: c'];
        yield 'cursor in loop' => ['LOOP OPEN c; END LOOP;', 'Undefined CURSOR: c'];
        yield 'outer cursor in loop' => ['DECLARE c CURSOR FOR SELECT 1; LOOP OPEN c; END LOOP;', null];
        yield 'duplicate variable' => ['DECLARE x INT; DECLARE x INT;', 'Duplicate variable: x'];
        yield 'call identity before argument' => ['SELECT ``(@@missing_variable);', "Incorrect routine name ''"];
        yield 'earlier statement before call' => ['SELECT @@missing_variable; SELECT ``();', "Unknown system variable 'missing_variable'"];
        yield 'unknown system variable'  => ['SELECT @@missing_variable;', "Unknown system variable 'missing_variable'"];
        yield 'declared condition' => ["DECLARE c CONDITION FOR SQLSTATE '45000'; SIGNAL c;", null];
        yield 'outer condition' => ["DECLARE c CONDITION FOR SQLSTATE '45000'; BEGIN SIGNAL c; END;", null];
        yield 'closed inner scope' => ["BEGIN DECLARE c CONDITION FOR SQLSTATE '45000'; END; SIGNAL c;", 'Undefined CONDITION: c'];
        yield 'deferred column binding' => ['SELECT missing_column;', null];
        yield 'rejected expression' => ['SELECT CAST(1 AT LOCAL AS DATE);', "This version of MySQL doesn't yet support 'AT LOCAL'."];
    }

    #[DataProvider('providerPrefixes')]
    public function testBeforeResolvesCompletedStatementsInTheirScopes(string $prefix, ?string $message): void
    {
        $sql = 'CREATE PROCEDURE p() BEGIN ' . $prefix . " SIGNAL SQLSTATE '00000'; END";
        $end = strpos($sql, "'00000'");
        self::assertNotFalse($end);
        $rule = new InputPrefix(new Lowering(Productions::load(__DIR__ . '/../../../../../resources/productions/mysql-8.4.7.php'), new Leaves(), new LanguageProfile(GrammarRelease::MySql847)));
        $failure = $rule->before((new MySqlParser('mysql-8.4.7'))->parse($sql), $end + 7);

        self::assertSame($message, $failure?->problem->message());
        self::assertTrue($failure === null || $failure->end < $end);
    }

    public function testDiagnosticResolvesAConditionNameWithoutSourceText(): void
    {
        $rule = new InputPrefix(new Lowering(Productions::load(__DIR__ . '/../../../../../resources/productions/mysql-8.4.7.php'), new Leaves(), new LanguageProfile(GrammarRelease::MySql847)));

        self::assertSame('Undefined CONDITION: absent', $rule->diagnostic(new Block([], [new Signal(new ConditionName(new Name('absent')))]), new ParameterList([]))?->message());
        self::assertNull($rule->diagnostic(new Block(), new ParameterList([])));
    }

    public function testBeforeKeepsAnOpenNestedScope(): void
    {
        $sql = "CREATE PROCEDURE p() BEGIN DECLARE c CONDITION FOR SQLSTATE '45000'; BEGIN SIGNAL c; SIGNAL SQLSTATE '00000'; END; END";
        $end = strpos($sql, "'00000'");
        self::assertNotFalse($end);
        $rule = new InputPrefix(new Lowering(Productions::load(__DIR__ . '/../../../../../resources/productions/mysql-8.4.7.php'), new Leaves(), new LanguageProfile(GrammarRelease::MySql847)));

        self::assertNull($rule->before((new MySqlParser('mysql-8.4.7'))->parse($sql), $end + 7));
    }
    public function testCompletedRetainsAParameterRefusalBeforeALaterCondition(): void
    {
        $sql = "CREATE PROCEDURE p(a INT, A INT) BEGIN SIGNAL SQLSTATE '00000'; END";
        $rule = new InputPrefix(new Lowering(Productions::load(__DIR__ . '/../../../../../resources/productions/mysql-8.4.7.php'), new Leaves(), new LanguageProfile(GrammarRelease::MySql847)));
        $failure = $rule->before((new MySqlParser('mysql-8.4.7'))->parse($sql), strlen($sql));

        self::assertSame('Duplicate parameter: A', $failure?->problem->message());
    }

}

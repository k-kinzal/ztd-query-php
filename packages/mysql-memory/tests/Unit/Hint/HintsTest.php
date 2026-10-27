<?php

declare(strict_types=1);

namespace Tests\Unit\Hint;

use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Hint\Hints;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\State\StatementCounters;
use MySqlMemory\Session\Statement\Binding;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteral;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteralKind;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\VariableHint;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use Tests\Generator\Bindings;
use Tests\Generator\Embedding;

#[CoversClass(Hints::class)]
#[Small]
final class HintsTest extends TestCase
{
    public function testTypingTypesTheStatementWithTheValuesOfSetVar(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT /*+ SET_VAR(div_precision_increment = 10) */ 1/3')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0.3333333333']], $result->rows);
        self::assertSame(4, $session->variables->read('div_precision_increment'));
    }

    public function testTypingValueReadsTheOriginalStructureWithoutInputText(): void
    {
        $session = (new Instance())->connect();
        $session->text = 'unrelated invalid input';
        $query = new QueryRoot(new Select([], [new SelectExpression(new NumberLiteral('1'))], hints: [new VariableHint('div_precision_increment', new HintLiteral(HintLiteralKind::Integer, '10'))]));
        $application = (new Hints())->typingValue($query, $session);

        self::assertSame(10, $session->variables->read('div_precision_increment'));
        self::assertSame([], $application->warnings);
        $application->restore($session);
        self::assertSame(4, $session->variables->read('div_precision_increment'));
    }

    public function testApplySetsTheVariablesWhileTheStatementRuns(): void
    {
        $session = (new Instance())->connect();
        $during = $session->query('SELECT /*+ SET_VAR(sort_buffer_size = 2M) SET_VAR(sort_buffer_size = 3M) SET_VAR(autocommit = 0) SET_VAR(nosuch = 1) */ @@sort_buffer_size')[0];
        $warnings = $session->diagnostics->conditions;
        $after = $session->query('SELECT @@sort_buffer_size')[0];

        self::assertInstanceOf(ResultSet::class, $during);
        self::assertSame([['2097152']], $during->rows);
        self::assertSame([
            ['Warning', 3126, 'Hint SET_VAR(sort_buffer_size=3145728)  is ignored as conflicting/duplicated'],
            ['Warning', 3637, "Variable 'autocommit' cannot be set using SET_VAR hint."],
            ['Warning', 3128, "Unresolved name 'nosuch' for SET_VAR hint"],
        ], $warnings);
        self::assertInstanceOf(ResultSet::class, $after);
        self::assertSame([['262144']], $after->rows);
    }

    public function testApplyRestoresTheVariablesWhenTheStatementFails(): void
    {
        $session = (new Instance())->connect();
        $session->run('SELECT /*+ SET_VAR(sql_mode = ANSI) SET_VAR(sql_select_limit = 1) */ 1 FROM nosuch.t');

        self::assertSame([1049, 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'], [$session->diagnostics->conditions[0][1], $session->variables->read('sql_mode')]);
    }

    public function testApplyChangesNothingInAStoredFunction(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE FUNCTION f() RETURNS INT DETERMINISTIC RETURN (SELECT /*+ SET_VAR(sort_buffer_size = 100000) */ @@sort_buffer_size)');
        $result = $session->query('SELECT f()')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['262144']], $result->rows);
    }

    public function testPrepareWarnsWhenTheStatementIsPreparedAndExecuteOnlyAboutValues(): void
    {
        $session = (new Instance())->connect();
        $session->query("PREPARE s FROM 'SELECT /*+ FOO */ 1'");
        $prepared = $session->diagnostics->conditions;
        $session->query("PREPARE s FROM 'SELECT /*+ SET_VAR(sort_buffer_size = 100000) SET_VAR(sort_buffer_size = ON) BKA(x) */ @@sort_buffer_size'");
        $again = array_column($session->diagnostics->conditions, 1);
        $result = $session->query('EXECUTE s')[0];

        self::assertSame([['Warning', 1064, "Optimizer hint syntax error near 'FOO */ 1' at line 1"]], $prepared);
        self::assertSame([3126, 3128], $again);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[['100000']], []], [$result->rows, $session->diagnostics->conditions]);
    }

    public function testResolveWarnsAboutNamesTheBlocksDoNotHave(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT PRIMARY KEY, b INT, KEY kb (b))');
        $session->query('SELECT /*+ BKA(t) BKA(t) INDEX(t kb, kx) */ 1 FROM t WHERE a IN (SELECT /*+ BKA(@`select#1` x) */ 1)');

        self::assertSame([
            ['Warning', 3126, 'Hint BKA(`t` ) is ignored as conflicting/duplicated'],
            ['Warning', 3128, 'Unresolved name `x`@`select#1` for BKA hint'],
            ['Warning', 3128, 'Unresolved name `t`@`select#1` `kx` for INDEX hint'],
        ], $session->diagnostics->conditions);
    }

    public function testResolveComesAfterAMissingTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->run('SELECT /*+ FOO */ 1 FROM nosuch');
        $missing = $session->diagnostics->conditions;
        $session->run('SELECT /*+ BKA(x) */ 1 FROM (SELECT 1 a) t WHERE nocol = 1');

        self::assertSame([[1064, 1146], [3128, 1054]], [array_column($missing, 1), array_column($session->diagnostics->conditions, 1)]);
    }

    public function testViewKeepsTheIndexHintsOfTheView(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT, KEY ka (a), KEY kb (b))');
        $session->query('CREATE VIEW v AS SELECT /*+ BKA(t) QB_NAME(q) INDEX(t ka, kb) INDEX(x ka) */ a FROM t WHERE a IN (SELECT /*+ NO_INDEX(t) */ a FROM t)');
        $warnings = $session->diagnostics->conditions;
        $result = $session->query('SHOW CREATE VIEW v')[0];

        self::assertSame([['Warning', 3128, 'Unresolved name `x`@`select#1` for INDEX hint'], ['Warning', 3128, 'Unresolved name `x`@`select#1` `ka` for INDEX hint']], $warnings);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame('CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY DEFINER VIEW `v` AS select /*+ NO_INDEX(`t`@`select#2`) INDEX(`t`@`select#1` `ka`, `kb`) */ `t`.`a` AS `a` from `t` where `t`.`a` in (select `t`.`a` from `t`)', $result->rows[0][1]);
    }

    public function testRegistrationReadsTheHintsOfAStatement(): void
    {
        $session = (new Instance())->connect();
        $registration = (new Hints())->registration($session->analyze('SELECT /*+ MAX_EXECUTION_TIME(5) RESOURCE_GROUP(g) */ 1'), $session);

        self::assertSame(['5', 'g', []], [$registration->time?->milliseconds, $registration->group?->group, $registration->warnings]);
    }

    public function testHintedTellsWhetherHintsAreRead(): void
    {
        $session = (new Instance())->connect();
        $session->analyze('SELECT 1');
        $plain = (new Hints())->hinted($session);
        $session->analyze('SELECT /*+ BKA(t) */ 1');

        self::assertSame([true, true], [$plain, (new Hints())->hinted($session)]);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerTypedHintsPrecisions(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            foreach ([0, 1, 4, 10, 30] as $precision) {
                yield $release . ':' . $precision => [$release, $precision];
            }
        }
    }

    #[DataProvider('providerTypedHintsPrecisions')]
    public function testTypingValueOperationAndExecutionHonorTypedHintsBeforeDerivingDomains(string $release, int $precision): void
    {
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid input';
        $query = new QueryRoot(new Select([], [new SelectExpression(new Arithmetic(ArithmeticOperator::Divide, new NumberLiteral('1'), new NumberLiteral('3')))], hints: [new VariableHint('div_precision_increment', new HintLiteral(HintLiteralKind::Integer, (string) $precision))]));
        $statement1 = (new Embedding())->statement($query);
        $operation = (new Binding())->operation($session, $statement1, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        $statement2 = (new Embedding())->statement($query);
        $session->activity->begin();
        StatementCounters::received($session);

        $session->dots = [];
        $operation2 = (new Binding())->operation($session, $statement2, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
        $reply = (new Execution($session))->perform($operation2, (new Dispatcher())->command($operation2), []);
        self::assertNotSame($query, $operation);
        self::assertFalse($query->hasBinding());
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([[$precision === 0 ? '0' : '0.' . str_repeat('3', $precision)]], $reply->rows);
        self::assertSame($precision, $reply->columns[0]->decimals);
        self::assertSame(4, $session->variables->read('div_precision_increment'));
    }

    public function testMessagesCapturesDisplayTextWithoutChangingTheSemanticWarning(): void
    {
        $session = (new Instance())->connect();
        $input = 'SELECT /*+ FOO */ 1';
        $statement = $session->semantics()->analyze($input);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $statement);
        (new Hints())->messages($statement, $input, $session);
        $warning = $statement->statementInformation()->warnings[0];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintError::class, $warning);
        self::assertSame("Optimizer hint syntax error near 'FOO */ 1' at line 1", $session->preparation->hintMessages[$warning] ?? null);
        self::assertSame('Optimizer hint syntax error', $warning->message());
    }

    public function testWarningsEmitsGeneratedConditionsAtTheirRetainedStage(): void
    {
        $session = (new Instance())->connect();
        $first = new \SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintError(\SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintFailure::Syntax, 0, leading: true);
        $last = new \SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintError(\SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintFailure::Syntax, 0);
        (new Hints())->warnings([$first, $last], true, $session);
        self::assertSame([['Warning', 1064, 'Optimizer hint syntax error']], $session->diagnostics->conditions);
        (new Hints())->warnings([$first, $last], false, $session);
        self::assertCount(2, $session->diagnostics->conditions);
    }

}

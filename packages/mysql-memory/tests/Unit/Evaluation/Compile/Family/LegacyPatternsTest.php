<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile\Family;

use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Evaluation\Compile\Family\LegacyPatterns;
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
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Like;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use Tests\Support\Bindings;
use Tests\Support\Embedding;

#[CoversClass(LegacyPatterns::class)]
#[Small]
final class LegacyPatternsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerTexts(): iterable
    {
        yield 'compact' => ['20200102', 0];
        yield 'unpadded' => ['2020-1-2', 0];
        yield 'trailing' => ['2020-01-02trail', 1];
    }
    #[DataProvider('providerTexts')]
    public function testPrepareKeepsTheOriginalTextWithoutReadingSql(string $text, int $warnings): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->query('SET NAMES utf8');
        $session->text = 'unrelated invalid request';
        $pattern = new Like(new StringLiteral([$text]), new TemporalLiteral(TemporalForm::Date, '2020-01-02'));
        $query = new QueryRoot(new Select([], [new SelectExpression($pattern)]));
        $statement1 = (new Embedding())->statement($query);
        $session->activity->begin();
        StatementCounters::received($session);

        $session->dots = [];
        $operation1 = (new Binding())->operation($session, $statement1, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        $result = (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1), []);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0']], $result->rows);
        self::assertCount($warnings, $session->diagnostics->conditions);
        self::assertSame('unrelated invalid request', $session->text);
    }
    public function testConstantWarnsBeforeAnyTableRowIsRead(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(d DATE)');
        $result = $session->query("SELECT d LIKE 'invalid' FROM t")[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([], $result->rows);
        self::assertSame([1292], array_column($session->diagnostics->conditions, 1));
    }
}

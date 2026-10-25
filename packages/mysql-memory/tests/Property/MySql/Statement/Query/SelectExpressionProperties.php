<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Query;

use JsonException;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\State\StatementCounters;
use MySqlMemory\Session\Statement\Binding;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Contract\LexicalSettings;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression as Model;
use SqlSemantics\Statement\Spelling\Layout;
use SqlSemantics\Statement\Spelling\Spelled;
use Tests\Generator\Bindings;
use Tests\Generator\Choices;
use Tests\Generator\Embedding;

/**
 * Samples the public semantic value and checks its constructor contract.
 */
#[CoversNothing]
#[Large]
final class SelectExpressionProperties extends TestCase
{
    /**
     * @return iterable<string, array{non-negative-int}>
     */
    public static function providerSamples(): iterable
    {
        for ($sample = 0; $sample < (new \Tests\Generator\Samples())->count(); $sample++) {
            yield 'sample ' . $sample => [$sample];
        }
    }
    /**
     * @throws JsonException When a dependency inventory cannot be read
     * Every sampled constructor accepts arguments within its declared invariant domain.
     * @param non-negative-int $sample
     */
    #[Test]
    #[DataProvider('providerSamples')]
    public function satisfiesPublicValueInvariants(int $sample): void
    {
        $value = (new \Tests\Generator\Samples())->sample(self::MODEL, $sample);
        self::assertInstanceOf(self::MODEL, $value);
    }
    /**
     * The semantic value sampled by this property.
     */
    public const MODEL = Model::class;
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerLayoutSeeds(): iterable
    {
        for ($seed = 0; $seed < 100; $seed++) {
            yield (string) $seed => [(string) $seed];
        }
    }

    /**
     * Executes directly constructed expressions with independently chosen layout trivia.
     *
     * @throws \MySqlMemory\Error\SqlError When the constructed query cannot execute
     */
    #[DataProvider('providerLayoutSeeds')]
    #[Test]
    public function satisfiesConstructedLayoutsKeepTheValueAndColumnName(string $seed): void
    {
        $choices = new Choices('layout-structure:' . $seed);
        $left = $choices->integer(0, 100);
        $right = $choices->integer(0, 100);
        $release = $choices->one([GrammarRelease::MySql5651, GrammarRelease::MySql5744, GrammarRelease::MySql8044, GrammarRelease::MySql847, GrammarRelease::MySql910]);
        $mode = $choices->one(['', 'ANSI_QUOTES', 'NO_BACKSLASH_ESCAPES', 'IGNORE_SPACE', 'HIGH_NOT_PRECEDENCE,PIPES_AS_CONCAT']);
        $profile = new LanguageProfile($release, new LexicalSettings($mode));
        $layout = new Layout([new Spelled('', (string) $left), new Spelled($choices->one(['', '/* before */', "\t"]), '+'), new Spelled($choices->one(['', '/* after */', "\n"]), (string) $right)]);
        $expression = new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral((string) $left), new NumberLiteral((string) $right));
        $item = new Model($expression, layout: $layout, spellingProfile: $profile);
        $text = '\\' . $right;
        $plain = new Model(new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new StringLiteral([$text])));
        $quoted = $mode === 'NO_BACKSLASH_ESCAPES' ? $text : '\\' . $text;
        $session1 = (new Instance(substr($release->value, 6)))->connect();
        $session1->variables->session['sql_mode'] = $mode;
        $statement1 = (new Embedding())->statement(new Select([], [$item, $plain]));
        $session1->activity->begin();
        StatementCounters::received($session1);
        $session1->hinted = [];
        $session1->dots = [];
        $operation1 = (new Binding())->operation($session1, $statement1, [], new LanguageProfile($session1->semantics()->profile()->grammar, $session1->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        $reply = (new Execution($session1))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([[(string) ($left + $right), '1']], $reply->rows);
        self::assertSame($layout->text(), $reply->columns[0]->name);
        self::assertSame("1 + '" . $quoted . "'", $reply->columns[1]->name);
    }
}

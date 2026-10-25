<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Routine;

use JsonException;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Instance;
use MySqlMemory\Result\Batch;
use MySqlMemory\Result\ResultColumn;
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
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\ProgramVariable;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoPosition;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoVariables;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Parameter as Model;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
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
final class ParameterProperties extends TestCase
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
     * @return iterable<string, array{string, int}>
     */
    public static function providerVariableIdentityProfiles(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 30; $seed++) {
                yield $release . ':' . $seed => [$release, $seed];
            }
        }
    }

    /**
     * Exercises independently spelled references to generated parameter declarations.
     */
    #[DataProvider('providerVariableIdentityProfiles')]
    #[Test]
    public function satisfiesEquivalentNamesResolveAndRetainTheirResultSpelling(string $release, int $seed): void
    {
        $choices = new Choices('variable-identity:' . $seed);
        $parameters = $items = $arguments = $names = $targets = $writes = $written = [];
        $count = $choices->integer(1, 8);
        $profile = new LanguageProfile(GrammarRelease::from('mysql-' . $release));
        for ($position = 0; $position < $count; $position++) {
            [$declared, $read] = $choices->one([['É', 'e'], ['ñ', 'N'], ['Ü', 'u'], ['À', 'a'], ['ç', 'C']]);
            $parameters[] = new Model(new Name($declared . $position), new Integral(IntegralKind::Int));
            $names[] = $quoted = '`' . $read . $position . '`';
            $items[] = new SelectExpression(new ColumnUse(new Name($read . $position)), layout: new Layout([new Spelled('', $quoted)]), spellingProfile: $profile);
            $arguments[] = new NumberLiteral((string) $choices->integer(0, 100000));
            $targets[] = new ProgramVariable(new Name($read . $position));
            $written[] = $value = (string) $choices->integer(0, 100000);
            $writes[] = new SelectExpression(new NumberLiteral($value));
        }
        $session = (new Instance($release, [], ['d']))->connect('root', 'localhost', 'd');
        $session->text = 'unrelated invalid source';
        $name = new QualifiedName(new Name('p'));
        $assign = new QueryRoot(new Select([], $writes, into: new IntoVariables($targets), intoPosition: IntoPosition::AfterItems));
        $body = new Block([], [$assign, new QueryRoot(new Select([], $items))]);
        $statement1 = (new Embedding())->statement(new CreateProcedure($name, new ParameterList($parameters), $body));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation1 = (new Binding())->operation($session, $statement1, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        $statement2 = (new Embedding())->statement(new ProcedureCall($name, $arguments));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation2 = (new Binding())->operation($session, $statement2, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
        $called = (new Execution($session))->perform($operation2, (new Dispatcher())->command($operation2->statement), []);
        self::assertInstanceOf(Batch::class, $called);
        self::assertInstanceOf(ResultSet::class, $called->replies[0]);
        self::assertSame([$written], $called->replies[0]->rows);
        self::assertSame($names, array_map(static fn (ResultColumn $column): string => $column->name, $called->replies[0]->columns));
    }
}

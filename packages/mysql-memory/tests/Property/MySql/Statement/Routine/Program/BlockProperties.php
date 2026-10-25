<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Routine\Program;

use JsonException;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\State\StatementCounters;
use MySqlMemory\Session\Statement\Binding;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block as Model;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredEvaluation;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Generator\Bindings;
use Tests\Generator\Catalogue;
use Tests\Generator\Choices;
use Tests\Generator\Construction;
use Tests\Generator\Embedding;
use Tests\Generator\Metadata;

/**
 * Samples the public semantic value and checks its constructor contract.
 */
#[CoversNothing]
#[Large]
final class BlockProperties extends TestCase
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
    public static function providerDeferredEvaluationSeeds(): iterable
    {
        foreach (['5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 40; $seed++) {
                yield $release . ':' . $seed => [$release, $seed];
            }
        }
    }

    /**
     * @throws JsonException When the semantic inventory is malformed
     */
    #[DataProvider('providerDeferredEvaluationSeeds')]
    #[Test]
    public function satisfiesGeneratedProgramMemberDefersItsConditionUntilInvocation(string $release, int $seed): void
    {
        $body = (new Construction(new Choices('deferred-evaluation:' . $seed), new Catalogue(), new Metadata()))->object(DeferredEvaluation::class, 2);
        self::assertInstanceOf(DeferredEvaluation::class, $body);
        $session = (new Instance($release, [], ['d']))->connect('root', 'localhost', 'd');
        $session->text = 'unrelated invalid input';
        $name = new QualifiedName(new Name('p'));
        $statement1 = (new Embedding())->statement(new CreateProcedure($name, new ParameterList([]), $body));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation1 = (new Binding())->operation($session, $statement1, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        self::assertInstanceOf(Completion::class, (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), []));
        $failure = null;
        try {
            $statement2 = (new Embedding())->statement(new ProcedureCall($name));
            $session->activity->begin();
            StatementCounters::received($session);
            $session->hinted = [];
            $session->dots = [];
            $operation2 = (new Binding())->operation($session, $statement2, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
            (new Execution($session))->perform($operation2, (new Dispatcher())->command($operation2->statement), []);
        } catch (SqlError $error) {
            $failure = $error;
        }
        self::assertInstanceOf(SqlError::class, $failure);
        self::assertSame($body->problem()->message(), $failure->getMessage());
        self::assertSame('unrelated invalid input', $session->text);
    }
}

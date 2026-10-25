<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Utility\Set;

use JsonException;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Instance;
use MySqlMemory\Result\Batch;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
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
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Parameter;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\NameAssignment;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\PasswordAssignment;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetCharacterSet;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetItem;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetNames;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables as Model;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SystemAssignment;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\UserAssignment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Generator\Bindings;
use Tests\Generator\Catalogue;
use Tests\Generator\Choices;
use Tests\Generator\Embedding;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class SetVariablesProperties extends TestCase
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
     * Runs the generated analysis result through the ordinary dispatch and execution boundary.
     * @param non-negative-int $sample
     * @throws JsonException When the semantic type inventory cannot be read
     */
    #[Test]
    #[DataProvider('providerSamples')]
    public function satisfiesPublicValueInvariants(int $sample): void
    {
        $value = (new \Tests\Generator\Samples())->sample(self::MODEL, $sample);
        $statement = (new Embedding())->statement($value);
        $operation = (new \Tests\Generator\Semantics\Statement\OperationGenerator())->from($statement, new Choices('context:' . $sample));
        $profile = $operation->profile();
        $database = $operation->context->searchPath[0]->value;
        $mode = (new \Tests\Generator\Profiles())->mode($profile);
        $session = (new Instance(substr($profile->grammar->value, 6), ['sql_mode' => $mode], [$database]))->connect(database: $database);
        $parameters = array_fill(0, (new Bindings())->count($operation->statement), [null, \MySqlMemory\Typing\Domain::null()]);
        try {
            $reply = (new Execution($session))->perform($operation, (new Dispatcher())->command($operation->statement), $parameters);
            self::assertGreaterThanOrEqual(0, $reply->warnings());
        } catch (\MySqlMemory\Error\SqlError $error) {
            self::assertTrue($error->signalled !== null || !in_array($error->error, [\MySqlMemory\Error\Family\StatementError::UnknownError, \MySqlMemory\Error\Family\StatementError::NotSupportedYet], true), self::MODEL . ': ' . $error->getMessage());
        }
    }
    /**
     * The semantic value sampled by this property.
     */
    public const MODEL = Model::class;
    /**
     * @throws JsonException When the semantic inventory cannot be loaded
     */
    #[Test]
    public function satisfiesSetItemInventoryContainsOnlyAcceptedAssignmentRoles(): void
    {
        self::assertEqualsCanonicalizing([NameAssignment::class, PasswordAssignment::class, SetCharacterSet::class, SetNames::class, SystemAssignment::class, UserAssignment::class], (new Catalogue())->implementations(SetItem::class));
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerSetItemsProfiles(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 20; $seed++) {
                yield $release . ':' . $seed => [$release, $seed];
            }
        }
    }

    /**
     * Composed SET items update their distinct connection and result settings.
     */
    #[DataProvider('providerSetItemsProfiles')]
    #[Test]
    public function satisfiesConstructedCharacterSetItemsUpdateTheirDeclaredSessionState(string $release, int $seed): void
    {
        $choices = new Choices('set-character-set:' . $seed);
        $first = $choices->one(['latin1', 'utf8mb4', 'binary']);
        $second = $choices->one(['latin1', 'utf8mb4', 'binary']);
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid input';
        $statement = new Model([new SetNames(new CharsetName(new Name($first))), new SetCharacterSet(new CharsetName(new Name($second)))]);
        $statement1 = (new Embedding())->statement($statement);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation1 = (new Binding())->operation($session, $statement1, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        self::assertInstanceOf(Completion::class, (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), []));
        self::assertSame($second, $session->variables->read('character_set_client'));
        self::assertSame($second, $session->variables->read('character_set_results'));
        self::assertSame($session->variables->read('character_set_database'), $session->variables->read('character_set_connection'));
    }

    /**
     * Local variable identity does not depend on the rejected unquoted input form.
     */
    #[DataProvider('providerSetItemsProfiles')]
    #[Test]
    public function satisfiesConstructedLocalNamesAssignmentsRemainExecutable(string $release, int $seed): void
    {
        $choices = new Choices('set-local-names:' . $seed);
        $name = new Name($choices->one(['names', 'NAMES', 'namés']));
        $value = (string) $choices->integer(0, 100000);
        $session = (new Instance($release, [], ['d']))->connect('root', 'localhost', 'd');
        $session->text = 'unrelated invalid input';
        $procedure = new QualifiedName(new Name('p'));
        $body = new Block([], [new Model([new NameAssignment($name, new NumberLiteral($value))]), new QueryRoot(new Select([], [new SelectExpression(new ColumnUse($name))]))]);
        $statement2 = (new Embedding())->statement(new CreateProcedure($procedure, new ParameterList([new Parameter($name, new Integral(IntegralKind::Int))]), $body));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation2 = (new Binding())->operation($session, $statement2, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
        (new Execution($session))->perform($operation2, (new Dispatcher())->command($operation2->statement), []);
        $statement3 = (new Embedding())->statement(new ProcedureCall($procedure, [new NumberLiteral('0')]));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation3 = (new Binding())->operation($session, $statement3, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement3)));
        $reply = (new Execution($session))->perform($operation3, (new Dispatcher())->command($operation3->statement), []);
        self::assertInstanceOf(Batch::class, $reply);
        self::assertInstanceOf(ResultSet::class, $reply->replies[0]);
        self::assertSame([[$value]], $reply->replies[0]->rows);
    }
}

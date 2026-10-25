<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement;

use JsonException;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\State\StatementCounters;
use MySqlMemory\Session\Statement\Binding;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\QueryRoot as Model;
use Tests\Generator\Bindings;
use Tests\Generator\Choices;
use Tests\Generator\Embedding;
use Tests\Generator\Profiles;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class QueryRootProperties extends TestCase
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
        $mode = (new Profiles())->mode($profile);
        $session = (new Instance(substr($profile->grammar->value, 6), ['sql_mode' => $mode], [$database]))->connect(database: $database);
        $parameters = array_fill(0, (new Bindings())->count($operation->statement), [null, Domain::null()]);
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
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerStatementRolesFilteredOperands(): iterable
    {
        foreach (['5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 12; $seed++) {
                $choices = new Choices('query-roles:' . $seed);
                yield $release . '/' . $seed => [$release, $choices->one(['0', '1', '42', '2147483648']), $choices->one(['0', '1'])];
            }
        }
    }
    /**
     * Generated nested filters use operand syntax even when the release restricts standalone statements.
     */
    #[DataProvider('providerStatementRolesFilteredOperands')]
    #[Test]
    public function satisfiesFilteredQueryOperandsExecuteWithoutStatementRestrictions(string $release, string $value, string $condition): void
    {
        $body = new Select([], [new SelectExpression(new NumberLiteral($value))], where: new NumberLiteral($condition));
        $options = str_starts_with($release, '5.') ? [SelectOption::Cache] : [];
        $root = new Model(new Select($options, [new SelectExpression(new ScalarSubquery($body))]));
        $session1 = (new Instance($release))->connect();
        $statement1 = (new Embedding())->statement($root);
        $session1->activity->begin();
        StatementCounters::received($session1);
        $session1->hinted = [];
        $session1->dots = [];
        $operation1 = (new Binding())->operation($session1, $statement1, [], new LanguageProfile($session1->semantics()->profile()->grammar, $session1->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        $result = (new Execution($session1))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([[$condition === '0' ? null : $value]], $result->rows);
    }
    /**
     * The generated named parameter keeps its binding style and reaches the result unchanged.
     */
    #[Test]
    public function satisfiesRetainsNamedParameters(): void
    {
        $node = new Parameter(':input');
        $profiles = new Profiles();
        $profile = $profiles->select($node);
        $session2 = (new Instance(substr($profile->grammar->value, 6)))->connect();
        $session2->variables->session['sql_mode'] = $profiles->mode($profile);
        $statement2 = (new Embedding())->statement($node);
        $parameters2 = [[7, Domain::integer()]];
        $session2->activity->begin();
        StatementCounters::received($session2);
        $session2->hinted = [];
        $session2->dots = [];
        $operation2 = (new Binding())->operation($session2, $statement2, $parameters2, new LanguageProfile($session2->semantics()->profile()->grammar, $session2->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
        $reply = (new Execution($session2))->perform($operation2, (new Dispatcher())->command($operation2->statement), $parameters2);
        self::assertSame(ParameterStyle::Named, $profile->parameters);
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([['7']], $reply->rows);
    }
}

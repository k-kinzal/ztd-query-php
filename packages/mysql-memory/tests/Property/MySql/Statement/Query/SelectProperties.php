<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Query;

use JsonException;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
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
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select as Model;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use Tests\Generator\Bindings;
use Tests\Generator\Catalogue;
use Tests\Generator\Choices;
use Tests\Generator\Construction;
use Tests\Generator\Embedding;
use Tests\Generator\Metadata;
use Tests\Generator\Profiles;
use WeakReference;

/**
 * Samples the public semantic value and checks its constructor contract.
 */
#[CoversNothing]
#[Large]
final class SelectProperties extends TestCase
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
     * @param non-negative-int $sample
     * @throws JsonException When a semantic inventory cannot be read
     */
    #[Test]
    #[DataProvider('providerSamples')]
    public function satisfiesPublicValueInvariants(int $sample): void
    {
        $query = (new \Tests\Generator\Samples())->sample(self::MODEL, $sample);
        self::assertInstanceOf(self::MODEL, $query);
        $profile = (new \Tests\Generator\QueryProfiles())->select($query);
        $session = (new Instance(substr($profile->grammar->value, 6)))->connect();
        $session->variables->session['sql_mode'] = (new Profiles())->mode($profile);
        $parameters = array_fill(0, (new Bindings())->count($query), [null, Domain::null()]);
        $domains = array_map(static fn (array $parameter) => $parameter[1]->resolved(), $parameters);
        $context = \SqlSemantics\Contract\Platforms::of('mysql')->context($profile, null, [], true)->withSession($session->resolution($domains));
        try {
            $body = $query;
            while ($body instanceof \SqlSemantics\Platform\MySql\Statement\Query\QueryExpression || $body instanceof \SqlSemantics\Platform\MySql\Statement\Query\QueryStatement || $body instanceof \SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery) {
                $body = $body instanceof \SqlSemantics\Platform\MySql\Statement\Query\QueryExpression ? $body->body : $body->query;
            }
            if ($body instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Insert\ValuesClauses) {
                $values = (new \SqlSemantics\Platform\MySql\Rules\Dml\ValueSources())->values($query);
                self::assertNotNull($values);
                $width = max(1, count($values->rows[0]->values));
                $columns = array_map(static fn (int $index): string => 'c' . $index . ' VARCHAR(255)', range(1, $width));
                $session->query('CREATE DATABASE pbt; USE pbt; CREATE TABLE target (' . implode(', ', $columns) . ')');
                $statement = new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery(new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto(false, null, false, new \SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget(new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('target')))), $query);
                $operation = (new Binding())->operation($session, $statement, $parameters, $profile);
                $reply = (new Execution($session))->perform($operation, (new Dispatcher())->command($statement), $parameters);
                self::assertGreaterThanOrEqual(0, $reply->warnings());
                return;
            }
            $derivation = (new \MySqlMemory\Session\Resolution\Parameters())->derivation($query, $context, $domains);
            $derivation->query($query, $derivation->environment());
            $facts = $derivation->facts();
            (new \MySqlMemory\Session\Problems())->raise(new \MySqlMemory\Session\Resolution\QueryPreparation($query, $facts, $profile), $session);
            $evaluation = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
            $connection = new \MySqlMemory\Evaluation\Compile\Connection($session->variables, $evaluation, $session->user, $session->host, $session->id, $parameters, session: WeakReference::create($session));
            $planner = new \MySqlMemory\Plan\Planner($query, $facts, $session->settings(), $connection, $session->instance->dictionary);
            $plan = $planner->query($query, null);
            self::assertCount(count($plan->domains), $plan->names);
            $rows = (new \MySqlMemory\Iterator\Builder())->build($plan->root);
            $rows->init(new \MySqlMemory\Evaluation\Frame($evaluation, []));
            while (($row = $rows->read()) !== null) {
                self::assertCount($plan->root->width(), $row);
            }
        } catch (SqlError $error) {
            self::assertTrue($error->signalled !== null || !in_array($error->error, [StatementError::UnknownError, StatementError::NotSupportedYet], true), self::MODEL . ': ' . $error->getMessage());
        }
    }
    /**
     * The semantic value sampled by this property.
     */
    public const MODEL = Model::class;
    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function providerOccurrenceOperands(): iterable
    {
        foreach ([NumberLiteral::class, BooleanLiteral::class, NullLiteral::class, Parameter::class] as $class) {
            for ($seed = 0; $seed < 20; $seed++) {
                yield $class . ':' . $seed => [$class, 'occurrence:' . $seed];
            }
        }
    }

    /**
     * @param class-string $class The constructor domain to sample
     * @throws JsonException When the semantic inventory is invalid
     */
    #[DataProvider('providerOccurrenceOperands')]
    #[Test]
    public function satisfiesSharedGeneratedOperandIsRejectedByTheStatementConstructor(string $class, string $seed): void
    {
        $operand = (new Construction(new Choices($seed), new Catalogue(), new Metadata()))->object($class, 4);
        self::assertInstanceOf(Scalar::class, $operand);
        $this->expectExceptionMessage('A statement node occurs at one position only.');
        new Model([], [new SelectExpression(new Grouped($operand)), new SelectExpression(new Grouped($operand))]);
    }

    /**
     * @param class-string $class The constructor domain to sample
     * @throws JsonException When the semantic inventory is invalid
     */
    #[DataProvider('providerOccurrenceOperands')]
    #[Test]
    public function satisfiesSeparatelyConstructedEqualOperandsExecuteDirectly(string $class, string $seed): void
    {
        $catalogue = new Catalogue();
        $metadata = new Metadata();
        $left = (new Construction(new Choices($seed), $catalogue, $metadata))->object($class, 4);
        $right = (new Construction(new Choices($seed), $catalogue, $metadata))->object($class, 4);
        self::assertInstanceOf(Scalar::class, $left);
        self::assertInstanceOf(Scalar::class, $right);
        self::assertNotSame($left, $right);
        self::assertEquals($left, $right);
        $session1 = (new Instance('8.4.7'))->connect();
        $statement1 = (new Embedding())->statement(new Model([], [new SelectExpression(new Grouped($left)), new SelectExpression(new Grouped($right))]));
        $session1->activity->begin();
        StatementCounters::received($session1);
        $session1->hinted = [];
        $session1->dots = [];
        $operation1 = (new Binding())->operation($session1, $statement1, [], new LanguageProfile($session1->semantics()->profile()->grammar, $session1->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        $reply = (new Execution($session1))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertCount(1, $reply->rows);
        self::assertCount(2, $reply->rows[0]);
        self::assertSame($reply->rows[0][0], $reply->rows[0][1]);
    }

    /**
     * Unresolved input names produce an ordinary SQL error rather than rejecting the generated model.
     */
    #[Test]
    public function satisfiesDoesNotRequireResolvableNamesOrSuccessfulExecution(): void
    {
        $node = new Model([], [new SelectExpression(new ColumnUse(new Name('missing')))]);
        $profile = (new Profiles())->select($node);
        self::assertSame('mysql', $profile->grammar->database());
        self::assertSame(ParameterStyle::Native, $profile->parameters);
        $subject = $node;
        $profile = (new Profiles())->select($subject);
        $session = (new Instance(substr($profile->grammar->value, 6)))->connect();
        $session->variables->session['sql_mode'] = (new Profiles())->mode($profile);
        $statement = (new Embedding())->statement($subject);
        $parameters = array_fill(0, (new Bindings())->count($statement), [null, Domain::null()]);
        try {
            $operation = (new Binding())->operation($session, $statement, $parameters, $profile);
            $reply = (new Execution($session))->perform($operation, (new Dispatcher())->command($operation->statement), $parameters);
            self::assertGreaterThanOrEqual(0, $reply->warnings());
        } catch (SqlError $failure) {
            self::assertTrue($failure->signalled !== null || !in_array($failure->error, [StatementError::UnknownError, StatementError::NotSupportedYet], true), $failure->getMessage());
        }
    }
}

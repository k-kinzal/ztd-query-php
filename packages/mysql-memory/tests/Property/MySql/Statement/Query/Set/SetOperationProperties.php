<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Query\Set;

use JsonException;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Session\Resolution\Parameters;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\Platforms;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation as Model;
use Tests\Generator\Bindings;
use Tests\Generator\Catalogue;
use Tests\Generator\Choices;
use Tests\Generator\Construction;
use Tests\Generator\Metadata;
use Tests\Generator\Profiles;
use Tests\Generator\QueryProfiles;
use WeakReference;

/**
 * Samples the public semantic value and checks its constructor contract.
 */
#[CoversNothing]
#[Large]
final class SetOperationProperties extends TestCase
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
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSamples')]
    public function satisfiesPublicValueInvariants(int $sample): void
    {
        $query = (new \Tests\Generator\Samples())->sample(self::MODEL, $sample);
        self::assertInstanceOf(self::MODEL, $query);
        $profile = (new QueryProfiles())->select($query);
        $session = (new Instance(substr($profile->grammar->value, 6)))->connect();
        $session->variables->session['sql_mode'] = (new Profiles())->mode($profile);
        $parameters = array_fill(0, (new Bindings())->count($query), [null, Domain::null()]);
        $domains = array_map(static fn (array $parameter) => $parameter[1]->resolved(), $parameters);
        $context = Platforms::of('mysql')->context($profile, null, [], true)->withSession($session->resolution($domains));
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
                $statement = new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery(new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto(false, null, false, new \SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('target')))), $query);
                $operation = (new \MySqlMemory\Session\Statement\Binding())->operation($session, $statement, $parameters, $profile);
                $reply = (new \MySqlMemory\Session\Execution($session))->perform($operation, (new \MySqlMemory\Command\Dispatcher())->command($statement), $parameters);
                self::assertGreaterThanOrEqual(0, $reply->warnings());
                return;
            }
            $derivation = (new Parameters())->derivation($query, $context, $domains);
            $derivation->query($query, $derivation->environment());
            $facts = $derivation->facts();
            (new \MySqlMemory\Session\Problems())->raise(new \MySqlMemory\Session\Resolution\QueryPreparation($query, $facts, $profile), $session);
            $evaluation = new Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
            $connection = new Connection($session->variables, $evaluation, $session->user, $session->host, $session->id, $parameters, session: WeakReference::create($session));
            $planner = new Planner($query, $facts, $session->settings(), $connection, $session->instance->dictionary);
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
     * @throws JsonException When the semantic constructor inventory cannot be read
     */
    #[Test]
    public function satisfiesGeneratedSetOperandsCanBeConsumedWithoutStatementPromotion(): void
    {
        $catalogue = new Catalogue();
        $choices = new Choices('all-model-roots:111');
        $choices->one($catalogue->roots());
        $query = (new Construction($choices, $catalogue, new Metadata()))->object(Model::class, 6);
        self::assertInstanceOf(Model::class, $query);
        $profile = (new QueryProfiles())->select($query);
        $session = (new Instance(substr($profile->grammar->value, 6)))->connect();
        $session->variables->session['sql_mode'] = (new Profiles())->mode($profile);
        $parameters = array_fill(0, (new Bindings())->count($query), [null, Domain::null()]);
        $domains = array_map(static fn (array $parameter) => $parameter[1]->resolved(), $parameters);
        $context = Platforms::of('mysql')->context($profile, null, [], true)->withSession($session->resolution($domains));
        $derivation = (new Parameters())->derivation($query, $context, $domains);
        $derivation->query($query, $derivation->environment());
        $evaluation = new Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
        $connection = new Connection($session->variables, $evaluation, $session->user, $session->host, $session->id, $parameters, session: WeakReference::create($session));
        $planner = new Planner($query, $derivation->facts(), $session->settings(), $connection, $session->instance->dictionary);
        try {
            $plan = $planner->query($query, null);
            self::assertCount(count($plan->names), $plan->domains);
        } catch (SqlError $failure) {
            self::assertNotSame(StatementError::UnknownError, $failure->error);
            self::assertNotSame(StatementError::NotSupportedYet, $failure->error);
        }
    }
}

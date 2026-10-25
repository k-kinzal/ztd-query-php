<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement;

use JsonException;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\Statement\Binding;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\StartReplica;
use SqlSemantics\Platform\MySql\Statement\Statement as Model;
use SqlSemantics\Statement\Scalar;
use Tests\Generator\Bindings;
use Tests\Generator\Catalogue;
use Tests\Generator\Choices;
use Tests\Generator\Construction;
use Tests\Generator\Embedding;
use Tests\Generator\Metadata;
use Tests\Generator\Profiles;

/**
 * Generates members of the semantic interface and consumes their original values in MemoryDB.
 */
#[CoversNothing]
#[Large]
final class StatementProperties extends TestCase
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
        } catch (SqlError $error) {
            self::assertTrue($error->signalled !== null || !in_array($error->error, [StatementError::UnknownError, StatementError::NotSupportedYet], true), self::MODEL . ': ' . $error->getMessage());
        }
    }

    /**
     * The semantic interface inhabited by each generated sample.
     */
    public const MODEL = Model::class;

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerProfileRepairedModels(): iterable
    {
        yield 'condition operand' => ['e91a273aff1ba87d6d5a79bfff108f6d', 153];
        yield 'create table options' => ['e91a273aff1ba87d6d5a79bfff108f6d', 298];
        yield 'late subquery limit' => ['6a7d1bc45f092c61dc9b56b4d0676cd1', 121];
        yield 'relay log position' => ['6a7d1bc45f092c61dc9b56b4d0676cd1', 609];
        yield 'explain modern options' => ['0a00fd08a150854e056cde648d20c202', 129];
        yield 'replication option list' => ['0a00fd08a150854e056cde648d20c202', 605];
    }
    /**
     * @throws JsonException When a semantic inventory is invalid
     */
    #[DataProvider('providerProfileRepairedModels')]
    #[Test]
    public function satisfiesFindsAProfileForTheConstructedStatement(string $seed, int $index): void
    {
        $catalogue = new Catalogue();
        $choices = new Choices($seed . ':' . $index);
        $class = $choices->one($catalogue->roots());
        $node = (new Construction($choices, $catalogue, new Metadata()))->object($class, 6);
        self::assertTrue($node instanceof Model || $node instanceof Scalar);
        self::assertSame('mysql', (new Profiles())->select($node)->grammar->database());
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
    /**
     * @return iterable<string, array{class-string, int}>
     */
    public static function providerProfileCompositionModels(): iterable
    {
        yield 'delete comma lists' => [MultipleDelete::class, 302];
        yield 'replica position vocabulary' => [StartReplica::class, 601];
    }
    /**
     * @param class-string $class The root selected by the original whole-model campaign
     * @throws JsonException When a semantic inventory is invalid
     */
    #[DataProvider('providerProfileCompositionModels')]
    #[Test]
    public function satisfiesPreservesConstructedListComposition(string $class, int $index): void
    {
        $catalogue = new Catalogue();
        $choices = new Choices('entry-20261015:' . $index);
        $choices->one($catalogue->roots());
        $node = (new Construction($choices, $catalogue, new Metadata()))->object($class, 6);
        self::assertInstanceOf(Model::class, $node);
        self::assertSame('mysql', (new Profiles())->select($node)->grammar->database());
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
    /**
     * @return iterable<string, array{int}>
     */
    public static function providerCompositionTrials(): iterable
    {
        foreach ([699, 1994, 5420, 7950, 7974, 8332, 9946, 10282, 10875] as $index) {
            yield 'row-roles-20261017:' . $index => [$index];
        }
    }
    /**
     * @throws JsonException When the public constructor inventory is invalid
     */
    #[DataProvider('providerCompositionTrials')]
    #[Test]
    public function satisfiesAcceptedCompositionHasAPreservingLanguageProfile(int $index): void
    {
        $catalogue = new Catalogue();
        $choices = new Choices('row-roles-20261017:' . $index);
        $class = $choices->one($catalogue->roots());
        $node = (new Construction($choices, $catalogue, new Metadata()))->object($class, 6);
        self::assertInstanceOf(Model::class, $node);
        $profile = (new Profiles())->select($node);
        self::assertSame('mysql', $profile->grammar->database());
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

<?php

declare(strict_types=1);

namespace Tests\Property\Semantics\Statement;

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
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowParseTree;
use SqlSemantics\Statement\Operation as Model;
use SqlSemantics\Statement\Scalar;
use Tests\Generator\Bindings;
use Tests\Generator\Catalogue;
use Tests\Generator\Choices;
use Tests\Generator\Construction;
use Tests\Generator\Embedding;
use Tests\Generator\Metadata;
use Tests\Generator\Profiles;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class OperationProperties extends TestCase
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
        $operation = $value;
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
     * The semantic value sampled by this property.
     */
    public const MODEL = Model::class;

    /**
     * @return iterable<string, array{int}>
     */
    public static function providerConstructionAssignmentProfiles(): iterable
    {
        yield 'standalone password in a variable list' => [197];
        yield 'legacy expiration with a modern account name' => [982];
    }
    /**
     * @throws JsonException When the value inventory is invalid
     */
    #[DataProvider('providerConstructionAssignmentProfiles')]
    #[Test]
    public function satisfiesAssignmentConstructorDomainsHaveALanguageInterpretation(int $index): void
    {
        $catalogue = new Catalogue();
        $choices = new Choices('signatures-20261016:' . $index);
        $class = $choices->one($catalogue->roots());
        $node = (new Construction($choices, $catalogue, new Metadata()))->object($class, 6);
        self::assertInstanceOf(Statement::class, $node);
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
    public static function providerProfileIntervalAndUpgradeModels(): iterable
    {
        yield 'legacy database upgrade in a modern request' => [ShowParseTree::class, 483];
        yield 'unmarked row in a leading interval' => [GroupConcat::class, 519];
    }
    /**
     * @param class-string $class The selected root type
     * @throws JsonException When a semantic inventory is invalid
     */
    #[DataProvider('providerProfileIntervalAndUpgradeModels')]
    #[Test]
    public function satisfiesPreservesIntervalAndUpgradeComposition(string $class, int $index): void
    {
        $catalogue = new Catalogue();
        $choices = new Choices('alter-20261015:' . $index);
        $choices->one($catalogue->roots());
        $node = (new Construction($choices, $catalogue, new Metadata()))->object($class, 6);
        self::assertTrue($node instanceof Statement || $node instanceof Scalar);
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
    public static function providerProfileTimeCampaignModels(): iterable
    {
        yield 'legacy parameter with vector type' => [369];
        yield 'hexadecimal key prefix' => [429];
        yield 'legacy alter with engine attributes' => [513];
        yield 'default collation with transactional create' => [675];
        yield 'query destination inside scalar subquery' => [829];
    }
    /**
     * @throws JsonException When a semantic inventory is invalid
     */
    #[DataProvider('providerProfileTimeCampaignModels')]
    #[Test]
    public function satisfiesPreservesConstructorDomainsFromTheTimeCampaign(int $index): void
    {
        $catalogue = new Catalogue();
        $roots = $catalogue->roots();
        $choices = new Choices('time-20261015:' . $index);
        $choices->one($roots);
        $node = (new Construction($choices, $catalogue, new Metadata()))->object($roots[$index % count($roots)], 6);
        self::assertTrue($node instanceof Statement || $node instanceof Scalar);
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
}

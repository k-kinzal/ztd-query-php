<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Utility\Show\Server;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Window\RoutineVariable;
use SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest;
use SqlSemantics\Platform\MySql\Statement\Literal\SignedLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\ProgramVariable;
use SqlSemantics\Platform\MySql\Statement\Server\Plugin\OnWord;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\BareName;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowStatus as Model;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowVariables;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemTarget;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;
use SqlSemantics\Statement\Scalar;
use Tests\Generator\Catalogue;
use Tests\Generator\Choices;
use Tests\Generator\Construction;
use Tests\Generator\Metadata;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class ShowStatusProperties extends TestCase
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
        $statement = (new \Tests\Generator\Embedding())->statement($value);
        $operation = (new \Tests\Generator\Semantics\Statement\OperationGenerator())->from($statement, new Choices('context:' . $sample));
        $profile = $operation->profile();
        $database = $operation->context->searchPath[0]->value;
        $mode = (new \Tests\Generator\Profiles())->mode($profile);
        $session = (new \MySqlMemory\Instance(substr($profile->grammar->value, 6), ['sql_mode' => $mode], [$database]))->connect(database: $database);
        $parameters = array_fill(0, (new \Tests\Generator\Bindings())->count($operation->statement), [null, \MySqlMemory\Typing\Domain::null()]);
        try {
            $reply = (new \MySqlMemory\Session\Execution($session))->perform($operation, (new \MySqlMemory\Command\Dispatcher())->command($operation->statement), $parameters);
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
     * @return iterable<string, array{class-string, string}>
     */
    public static function providerOperandReports(): iterable
    {
        foreach ([Model::class, ShowVariables::class] as $class) {
            for ($seed = 0; $seed < 50; $seed++) {
                yield $class . ':' . $seed => [$class, (string) $seed];
            }
        }
    }

    /**
     * @param class-string $class
     * @throws JsonException When the value inventory is invalid
     */
    #[DataProvider('providerOperandReports')]
    #[Test]
    public function satisfiesReportScopeCannotBecomeASetOnlyScope(string $class, string $seed): void
    {
        $construction = new Construction(new Choices($seed), new Catalogue(), new Metadata());
        $statement = $construction->object($class, 5);
        self::assertTrue($statement instanceof Model || $statement instanceof ShowVariables);
        self::assertContains($statement->scope, [null, VariableScope::Global, VariableScope::Session]);
    }

    /**
     * @throws JsonException When the value inventory is invalid
     */
    #[Test]
    public function satisfiesGeneralExpressionsExcludePositionRestrictedOperands(): void
    {
        $scalars = (new Catalogue())->implementations(Scalar::class);
        self::assertNotContains(BareName::class, $scalars);
        self::assertNotContains(OnWord::class, $scalars);
        self::assertNotContains(RoutineVariable::class, $scalars);
        self::assertNotContains(DefaultRequest::class, $scalars);
        self::assertNotContains(SignedLiteral::class, $scalars);
        self::assertNotContains(OutputOrdinal::class, $scalars);
        self::assertNotContains(ProgramVariable::class, $scalars);
        self::assertNotContains(SystemTarget::class, $scalars);
    }
}

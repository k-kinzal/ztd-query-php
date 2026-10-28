<?php

declare(strict_types=1);

namespace Tests\Property\Statement;

use Eris\Generator;
use Eris\Generators;
use Eris\TestTrait;
use LogicException;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Statement\AnalyzedStatement;
use Tests\Generator\MySql\Statement\Routine\CreateProcedureGenerator;
use Tests\Generator\MySql\Statement\Routine\ParameterListGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\DeferredSelectionGenerator;
use Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;
use Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator;

/**
 * Executes generated stored expressions after their generated routine definitions.
 */
#[CoversNothing]
#[Large]
final class RoutineExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * Defines generated TEXT returns without applying the CHAR/VARCHAR restriction.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesBinaryTextReturnsInstallTheGeneratedDefinition(GrammarRelease $release): void
    {
        $this->forAll(\Tests\Generator\MySql\Statement\Routine\CreateFunctionGenerator::binaryTextReturns($release))->then(static function (\SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction $statement) use ($release): void {
            $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
            $session->text = 'Generated text-return declaration without SQL input';
            self::assertInstanceOf(Completion::class, (new Execution($session))->statement($statement));
            $stored = $session->instance->dictionary->schema('pbt')?->functions[$statement->name->name->value] ?? null;
            self::assertNotNull($stored);
            self::assertSame($statement, $stored->statement);
            self::assertSame($stored->charsets[2], $stored->returned()->collation->name);
            self::assertSame('Generated text-return declaration without SQL input', $session->text);
            self::assertSame([], $session->transaction->statements->running);
        });
    }

    /**
     * Keeps return-type conditions ahead of body errors and body notices.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesBinaryReturnConditionsPrecedeTheGeneratedBody(GrammarRelease $release): void
    {
        $this->forAll(\Tests\Generator\MySql\Statement\Routine\CreateFunctionGenerator::binaryReturns($release))->then(static function (array $parts) use ($release): void {
            [, $statement, $conditions] = $parts;
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction::class, $statement);
            $session = (new Instance(substr($release->value, 6), ['sql_mode' => ''], databases: ['pbt']))->connect(database: 'pbt');
            $session->text = 'Generated function declaration without SQL input';
            try {
                (new Execution($session))->statement($statement);
                self::fail('The generated binary return declaration must prevent definition.');
            } catch (SqlError $error) {
                self::assertSame(1235, $error->getCode());
                self::assertSame("This version of MySQL doesn't yet support 'return value collation'", $error->getMessage());
            }
            self::assertSame($conditions, array_column($session->diagnostics->conditions, 1));
            self::assertSame('Generated function declaration without SQL input', $session->text);
            self::assertSame([], $session->transaction->statements->running);
        });
    }

    /**
     * Executes declarations with an unbound cursor and preserves only earlier reading notices.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesDefinitionConditionsKeepOnlyThePrecedingGeneratedNotices(GrammarRelease $release): void
    {
        $this->forAll(\Tests\Generator\MySql\Statement\Routine\Program\Cursor\FetchCursorGenerator::definitions($release))->then(static function (array $parts) use ($release): void {
            [$statement, $message, $conditions] = $parts;
            self::assertInstanceOf(AnalyzedStatement::class, $statement);
            $session = (new Instance(substr($release->value, 6), ['sql_mode' => ''], databases: ['pbt']))->connect(database: 'pbt');
            $session->text = 'Generated declaration without SQL input';
            try {
                (new Execution($session))->statement($statement);
                self::fail('The generated definition has an unbound cursor.');
            } catch (SqlError $error) {
                self::assertSame(1324, $error->getCode());
                self::assertSame($message, $error->getMessage());
            }
            self::assertSame($conditions, array_column($session->diagnostics->conditions, 1));
            self::assertSame('Generated declaration without SQL input', $session->text);
            self::assertSame([], $session->transaction->statements->running);
        });
    }

    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerReleases(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() === 'mysql') {
                yield $release->value => [$release];
            }
        }
    }

    /**
     * Stored SQL accepts the definition and raises the reached operand condition on invocation.
     *
     * @param Generator<array{CreateProcedure, AnalyzedStatement}> $sequences Dependent definitions and calls
     */
    #[Test]
    #[DataProvider('providerInvocations')]
    public function satisfiesInvocationConsumesTheStoredOperands(Generator $sequences, int $expectedCode): void
    {
        $this->forAll($sequences)->then(static function (array $sequence) use ($expectedCode): void {
            [$definition, $call] = $sequence;
            self::assertInstanceOf(CreateProcedure::class, $definition);
            self::assertInstanceOf(ProcedureCall::class, $call);
            self::assertSame($definition->name, $call->procedure);
            $session = (new Instance('8.4.7', databases: ['pbt']))->connect(database: 'pbt');
            $session->text = 'Generated routine definition and call without SQL input';
            $execution = new Execution($session);
            self::assertInstanceOf(Completion::class, $execution->statement($definition));
            try {
                $execution->statement($call);
                self::fail('The generated invocation retains an invalid scalar operand.');
            } catch (SqlError $error) {
                self::assertSame($expectedCode, $error->getCode());
                self::assertNotSame('', $error->getMessage());
            }
            self::assertSame('Generated routine definition and call without SQL input', $session->text);
            self::assertSame([], $session->transaction->statements->running);
            self::assertSame([], $session->running->using);
        });
    }

    /**
     * @return iterable<string, array{Generator<array{CreateProcedure, AnalyzedStatement}>, int}>
     */
    public static function providerInvocations(): iterable
    {
        foreach (DeferredSelectionGenerator::operandForms() as $family => $bodies) {
            $definitions = new CreateProcedureGenerator(
                new QualifiedNameGenerator(new NameGenerator(Generators::map(static fn (int $value): string => 'routine_' . $value, Generators::choose(0, 100)), Generators::constant(null))),
                new ParameterListGenerator(Generators::constant([])),
                $bodies,
            );
            $sequences = Generators::map(static function (AnalyzedStatement $definition): array {
                if (!$definition instanceof CreateProcedure) {
                    throw new LogicException('The dependent call belongs to a generated procedure.');
                }
                $semantics = new Semantics(Dialect::MySql);
                $call = Semantics::fromStatement($semantics->context(), new ProcedureCall($definition->name, []));
                return [$definition, $call];
            }, new AnalyzedStatementGenerator(GrammarRelease::MySql847, $definitions));
            yield $family => [$sequences, match ($family) {
                'clock' => 1426, 'window exclusion', 'group concat window', 'json object window' => 1235, 'full text dependency' => 1054, 'frame interval' => 3587, default => 1241
            }];
        }
    }

    /**
     * The generated parameter row is visible in the stored read on each invocation.
     */
    #[Test]
    public function satisfiesGeneratedProgramRowReceivesItsCallArgument(): void
    {
        $this->forAll(\Tests\Generator\MySql\Statement\Routine\Program\ProgramRowGenerator::invocation())->then(/** @param array{CreateProcedure, ProcedureCall} $sequence */
            static function (array $sequence): void {
                [$definition, $call] = $sequence;
                self::assertInstanceOf(CreateProcedure::class, $definition);
                self::assertInstanceOf(ProcedureCall::class, $call);
                $session = (new Instance('8.4.7', databases: ['pbt']))->connect(database: 'pbt');
                $execution = new Execution($session);
                self::assertInstanceOf(Completion::class, $execution->statement($definition));
                $reply = $execution->statement($call);
                self::assertInstanceOf(\MySqlMemory\Result\Batch::class, $reply);
                self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $reply->replies[0]);
                self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral::class, $call->arguments[0]);
                self::assertSame([[$call->arguments[0]->text]], $reply->replies[0]->rows);
                self::assertSame($definition->parameters->parameters[0]->name->value, $reply->replies[0]->columns[0]->name);
                self::assertSame([], $session->running->using);
            }
        );
    }

}

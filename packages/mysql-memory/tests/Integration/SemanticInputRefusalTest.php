<?php

declare(strict_types=1);

namespace Tests\Integration;

use Eris\Generator;
use Eris\Generators;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\Parse\Entry\InputErrors;
use MySqlMemory\Session\Problem\Errors;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Statement\Fact\Diagnostic;
use Tests\Generator\MySql\Statement\Notice\DeprecationGenerator;
use Tests\Generator\MySql\Statement\Notice\ParseFailureGenerator;
use Tests\Generator\MySql\Statement\Notice\ParseStopGenerator;
use Tests\Generator\Semantics\Statement\Fact\DiagnosticGenerator;

/**
 * Generated semantic refusals reach the failure handling of a session without source SQL.
 */
#[CoversNothing]
#[Large]
final class SemanticInputRefusalTest extends TestCase
{
    /**
     * @param Generator<RejectedInput> $conditions Generated semantic refusals
     */
    #[Test]
    #[DataProvider('providerConditions')]
    public function testGeneratedRefusalsReachFailureHandling(GrammarRelease $release, Generator $conditions): void
    {
        $random = new \Eris\Random\RandomRange(new \Eris\Random\MersenneTwister());
        $random->seed(20261029);
        (new \Eris\Quantifier\ForAll([$conditions], 100, new \Eris\Shrinker\ShrinkerFactory(['timeLimit' => null]), 'multiple', $random))->withMaxSize(10)->then(static function (RejectedInput $condition) use ($release): void {
            $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
            $session->text = 'Semantic input failure supplied without source SQL';
            try {
                $error = $condition->problem instanceof \SqlSemantics\Platform\MySql\Statement\Alter\Problem\PartitionEntryRefused
                    ? (new \MySqlMemory\Session\Parse\Entry\PartitionInput())->error($condition, $session->text, $session)
                    : (new InputErrors())->refused($condition, $session->text, $session);
            } catch (SqlError $failure) {
                $error = $failure;
            }
            self::assertGreaterThan(0, $error->getCode());
            self::assertMatchesRegularExpression('/^[A-Z0-9]{5}$/D', $error->sqlState());
            self::assertNotSame('', $error->getMessage());
            (new Execution($session))->failed($error);
            self::assertSame(-1, $session->variables->rowCount);
            self::assertContains(['Error', $error->getCode(), $error->getMessage()], $session->diagnostics->conditions);
            self::assertSame([], $session->transaction->statements->running);
            self::assertSame('Semantic input failure supplied without source SQL', $session->text);
        });
    }

    /**
     * @return iterable<string, array{GrammarRelease, Generator<RejectedInput>}>
     */
    public static function providerConditions(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() !== 'mysql') {
                continue;
            }
            yield $release->value => [$release, Generators::map(static fn (Diagnostic $condition): RejectedInput => new RejectedInput($condition, 0), new DiagnosticGenerator($release))];
        }
    }

    #[Test]
    public function testCompletedStatementStopsBeforeLaterNotices(): void
    {
        $release = GrammarRelease::MySql847;
        $random = new \Eris\Random\RandomRange(new \Eris\Random\MersenneTwister());
        $random->seed(20261029);
        $conditions = Generators::tuple(
            Generators::suchThat(static fn (Deprecation $warning): bool => !$warning->aborts, new DeprecationGenerator($release)),
            Generators::suchThat(static fn (ParseFailure $failure): bool => !$failure->aborts, new ParseFailureGenerator($release)),
            new ParseStopGenerator($release),
            new DeprecationGenerator($release)
        );
        (new \Eris\Quantifier\ForAll([$conditions], 100, new \Eris\Shrinker\ShrinkerFactory(['timeLimit' => null]), 'multiple', $random))->then(
            static function (array $conditions): void {
                [$warning, $failure, $stop, $ignored] = $conditions;
                $session = (new Instance('8.4.7'))->connect();
                $session->text = 'Completed semantic conditions without SQL text';
                $syntax = \MySqlMemory\Error\Family\StatementError::ParseError->error('later input', 1);
                $expected = (new Errors())->error($failure->problem, $session);
                $error = (new \MySqlMemory\Session\Parse\ModernWarnings())->recorded([$warning, $failure, $stop, $ignored], $syntax, $session);
                self::assertSame($expected->getCode(), $error->getCode());
                self::assertSame([
                    ['Warning', $warning->code(), $warning->message()],
                    ['Error', $expected->getCode(), $expected->getMessage()],
                ], array_slice($session->diagnostics->conditions, 0, 2));
                (new Execution($session))->failed($error);
                self::assertSame(-1, $session->variables->rowCount);
                self::assertCount(2 + count($expected->following), $session->diagnostics->conditions);
                self::assertSame('Completed semantic conditions without SQL text', $session->text);
            }
        );
    }
}

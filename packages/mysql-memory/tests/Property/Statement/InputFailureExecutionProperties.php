<?php

declare(strict_types=1);

namespace Tests\Property\Statement;

use Eris\Generator;
use Eris\TestTrait;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\Parse\Entry\InputErrors;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Statement\Fact\Diagnostic;
use Tests\Generator\Semantics\Statement\Fact\DiagnosticGenerator;

/**
 * Executes semantic conditions at MemoryDB's input-refusal boundary.
 * Refused SQL forms cannot be published as ordinary statements. Their generated
 * dependencies reach the same error conversion and failure handling as SQL input,
 * without parsing or rendering a source statement in the property.
 */
#[CoversNothing]
#[Large]
final class InputFailureExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * @param Generator<RejectedInput> $conditions Generated semantic refusals and dependencies
     */
    #[Test]
    #[DataProvider('providerConditions')]
    public function satisfiesGeneratedRefusalsReachFailureHandling(GrammarRelease $release, Generator $conditions): void
    {
        $this->forAll($conditions)->withMaxSize(10)->then(static function (RejectedInput $condition) use ($release): void {
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
        foreach (DiagnosticGenerator::executionForms() as $name => [$release, $conditions]) {
            yield $name => [$release, \Eris\Generators::map(static fn (Diagnostic $condition): RejectedInput => new RejectedInput($condition, 0), $conditions)];
        }
    }

    /**
     * Completed typed notices determine error order without a SQL prefix to parse.
     */
    #[Test]
    public function satisfiesCompletedStatementStopsBeforeLaterNotices(): void
    {
        $this->forAll(\Tests\Generator\MySql\Statement\Notice\ParseFailureGenerator::completedConditions())->then(/** @param array{\SqlSemantics\Platform\MySql\Statement\Notice\NameConversion|\SqlSemantics\Platform\MySql\Statement\Notice\HeartbeatPeriod, \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure, \SqlSemantics\Platform\MySql\Statement\Notice\ParseStop, \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated} $conditions */
            static function (array $conditions): void {
                [$warning, $failure, $stop, $ignored] = $conditions;
                self::assertTrue($warning instanceof \SqlSemantics\Platform\MySql\Statement\Notice\NameConversion || $warning instanceof \SqlSemantics\Platform\MySql\Statement\Notice\HeartbeatPeriod);
                self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $failure);
                self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseStop::class, $stop);
                self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::class, $ignored);
                $session = (new Instance('8.4.7'))->connect();
                $session->text = 'Completed semantic conditions without SQL text';
                $syntax = \MySqlMemory\Error\Family\StatementError::ParseError->error('later input', 1);
                $error = (new \MySqlMemory\Session\Parse\ModernWarnings())->recorded([$warning, $failure, $stop, $ignored], $syntax, $session);
                self::assertSame(1273, $error->getCode());
                self::assertSame([
                    ['Warning', $warning->code(), $warning->message()],
                    ['Error', 1273, $failure->message()],
                ], $session->diagnostics->conditions);
                (new Execution($session))->failed($error);
                self::assertSame(-1, $session->variables->rowCount);
                self::assertCount(2, $session->diagnostics->conditions);
                self::assertSame('Completed semantic conditions without SQL text', $session->text);
            }
        );
    }

}

<?php

declare(strict_types=1);

namespace Tests\Property\Statement;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use Tests\Eris\TestTrait;
use Tests\Generator\MySql\Statement\StatementGenerator;

/**
 * Executes integer-position requests from generated semantic operands and conditions.
 */
#[CoversNothing]
#[Large]
final class NumericOperandExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * A refused number must fail before catalog lookup, installation or evaluation.
     *
     * @param 'explain'|'size'|'nodegroup'|'account'|'partition'|'subpartition'|'algorithm'|'weight'|'srid'|'spatial-create'|'spatial-drop'|'organization' $family The generated operand role
     */
    #[Test]
    #[DataProvider('providerRequests')]
    public function satisfiesNumericConditionsPrecedeExecution(GrammarRelease $release, string $family): void
    {
        $this->forAll(StatementGenerator::numericRequests($release, $family))->then(static function (\SqlSemantics\Statement\AnalyzedStatement $request) use ($release): void {
            $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
            $session->text = 'Generated numeric request without SQL';
            try {
                (new Execution($session))->statement($request);
                self::fail('An integer-position condition prevents executing the request.');
            } catch (SqlError $error) {
                self::assertSame(1064, $error->getCode());
            }
            self::assertSame([], $session->instance->dictionary->schema('pbt')?->tables);
            self::assertSame('Generated numeric request without SQL', $session->text);
            self::assertSame([], $session->transaction->statements->running);
        });
    }

    /**
     * @return iterable<string, array{GrammarRelease, string}>
     */
    public static function providerRequests(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() !== 'mysql') {
                continue;
            }
            $families = ['size', 'nodegroup', 'partition', 'subpartition', 'algorithm', 'weight'];
            if ($release !== GrammarRelease::MySql5651) {
                array_push($families, 'account', 'explain');
            }
            if (!in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
                array_push($families, 'srid', 'spatial-create', 'spatial-drop', 'organization');
            }
            foreach ($families as $family) {
                yield $release->value . ' ' . $family => [$release, $family];
            }
        }
    }

    /**
     * Stored conditions retain instruction order even in an unexecuted branch.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesStoredNumericConditionsRespectEarlierAssignments(GrammarRelease $release): void
    {
        $this->forAll(StatementGenerator::numericDefinitions($release))->then(static function (array $input) use ($release): void {
            [$definition, $code] = $input;
            self::assertInstanceOf(\SqlSemantics\Statement\AnalyzedStatement::class, $definition);
            $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
            $session->text = 'Generated stored numeric request without SQL';
            try {
                (new Execution($session))->statement($definition);
                self::fail('The first definition condition prevents installation.');
            } catch (SqlError $error) {
                self::assertSame($code, $error->getCode());
            }
            self::assertSame([], $session->instance->dictionary->schema('pbt')?->procedures);
            self::assertSame('Generated stored numeric request without SQL', $session->text);
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

}

<?php

declare(strict_types=1);

namespace Tests\Property\Statement;

use Eris\TestTrait;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Statement\AnalyzedStatement;
use Tests\Generator\MySql\Statement\StatementGenerator;

/**
 * Executes complete NAMES operands with generated assignment and instruction ordering.
 */
#[CoversNothing]
#[Large]
final class NamesAssignmentExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * Definition conditions precede assignments, even in an unreachable stored branch.
     */
    #[Test]
    #[DataProvider('providerRequests')]
    public function satisfiesCompleteAssignmentsRetainTheirFirstCondition(GrammarRelease $release, bool $stored): void
    {
        $this->forAll(StatementGenerator::namesRequests($release, $stored))->then(static function (array $input) use ($release): void {
            [$request, $code] = $input;
            self::assertInstanceOf(AnalyzedStatement::class, $request);
            $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
            $session->text = 'Generated keyword operands without SQL';
            try {
                (new Execution($session))->statement($request);
                self::fail('The definition condition prevents executing the assignments.');
            } catch (SqlError $error) {
                self::assertSame($code, $error->getCode());
            }
            self::assertSame([], $session->instance->dictionary->schema('pbt')?->procedures);
            self::assertSame([], $session->variables->user);
            self::assertSame('Generated keyword operands without SQL', $session->text);
            self::assertSame([], $session->transaction->statements->running);
        });
    }

    /**
     * @return iterable<string, array{GrammarRelease, bool}>
     */
    public static function providerRequests(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() === 'mysql') {
                yield $release->value . ' ordinary' => [$release, false];
                yield $release->value . ' stored' => [$release, true];
            }
        }
    }
}

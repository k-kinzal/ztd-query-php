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
use Tests\Generator\MySql\Statement\Partition\Method\HashMethodGenerator;

/**
 * Runs generated partition restrictions before database objects can be changed.
 */
#[CoversNothing]
#[Large]
final class PartitionExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * Session-dependent methods refuse both preparation and execution without SQL input.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesGeneratedSessionDependenceFailsBeforeTableLookup(GrammarRelease $release): void
    {
        $this->forAll(HashMethodGenerator::refusedRequests($release))->then(static function (AnalyzedStatement $statement) use ($release): void {
            $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
            $session->text = 'Generated partition request without SQL';
            try {
                (new \MySqlMemory\Session\Access\Preparation())->check($statement, $session);
                self::fail('A session-dependent partition method cannot be prepared.');
            } catch (SqlError $error) {
                self::assertSame(1064, $error->getCode());
            }
            try {
                (new Execution($session))->statement($statement);
                self::fail('A session-dependent partition method cannot execute.');
            } catch (SqlError $error) {
                self::assertSame(1064, $error->getCode());
            }
            self::assertSame('Generated partition request without SQL', $session->text);
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

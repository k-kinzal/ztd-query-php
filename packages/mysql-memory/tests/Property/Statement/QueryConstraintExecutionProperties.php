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
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use Tests\Eris\TestTrait;
use Tests\Generator\MySql\Statement\QueryRootGenerator;

/**
 * Executes complete generated reads whose output constraints prevent preparation.
 */
#[CoversNothing]
#[Large]
final class QueryConstraintExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * Keeps stars, ordinals, row operands and set widths behind their earlier dependencies.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesGeneratedReadConditionsUseTheirSemanticOperands(GrammarRelease $release): void
    {
        $this->forAll(QueryRootGenerator::constrainedReads($release))->then(static function (array $sample) use ($release): void {
            [, $statement, $code, $warnings] = $sample;
            self::assertInstanceOf(QueryRoot::class, $statement);
            self::assertIsInt($warnings);
            self::assertTrue($statement->hasExecutionCondition());
            $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
            $session->text = 'Generated query without SQL';
            try {
                (new \MySqlMemory\Session\Access\Preparation())->check($statement, $session);
                self::fail('The generated read condition must prevent preparation.');
            } catch (SqlError $error) {
                self::assertSame($code, $error->getCode());
            }
            try {
                (new Execution($session))->statement($statement);
                self::fail('The generated read condition must prevent execution.');
            } catch (SqlError $error) {
                self::assertSame($code, $error->getCode());
            }
            self::assertSame(array_fill(0, $warnings, 1287), array_column(array_filter($session->diagnostics->conditions, static fn (array $condition): bool => $condition[0] === 'Warning'), 1));
            self::assertSame('Generated query without SQL', $session->text);
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

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
use Tests\Generator\MySql\Statement\Utility\Explain\ExplainConnectionGenerator;

/**
 * Executes generated connection explanations and prepares the same semantic values.
 * Refused options precede connection lookup; ignored empty destinations reach it.
 */
#[CoversNothing]
#[Large]
final class ExplainExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * Connection options fail at their semantic boundary in both execution paths.
     */
    #[Test]
    #[DataProvider('providerConnectionReleases')]
    public function satisfiesGeneratedOptionsRefusePreparationAndExecution(GrammarRelease $release): void
    {
        $this->forAll(ExplainConnectionGenerator::refusedOptions($release))->then(static function (array $parts) use ($release): void {
            [$statement, $code, $preparedCode] = $parts;
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection::class, $statement);
            $session = (new Instance(substr($release->value, 6)))->connect();
            $session->text = 'Generated explanation without SQL';
            try {
                (new \MySqlMemory\Session\Access\Preparation())->check($statement, $session);
                self::fail('The generated options cannot be prepared.');
            } catch (SqlError $error) {
                self::assertSame($preparedCode, $error->getCode());
            }
            try {
                (new Execution($session))->statement($statement);
                self::fail('The generated options cannot execute.');
            } catch (SqlError $error) {
                self::assertSame($code, $error->getCode());
            }
            self::assertFalse($session->released);
            self::assertSame('Generated explanation without SQL', $session->text);
        });
    }

    /**
     * Report options fail before running the generated explained query.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesGeneratedStatementOptionsFailBeforeExecution(GrammarRelease $release): void
    {
        $this->forAll(\Tests\Generator\MySql\Statement\Utility\Explain\ExplainGenerator::refusedOptions($release))->then(static function (array $parts) use ($release): void {
            [$statement, $code] = $parts;
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain::class, $statement);
            $session = (new Instance(substr($release->value, 6)))->connect();
            $session->text = 'Generated statement explanation without SQL';
            try {
                (new Execution($session))->statement($statement);
                self::fail('The generated report options cannot execute.');
            } catch (SqlError $error) {
                self::assertSame($code, $error->getCode());
            }
            self::assertSame('Generated statement explanation without SQL', $session->text);
            self::assertFalse($session->released);
        });
    }

    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerConnectionReleases(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() === 'mysql' && $release !== GrammarRelease::MySql5651) {
                yield $release->value => [$release];
            }
        }
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

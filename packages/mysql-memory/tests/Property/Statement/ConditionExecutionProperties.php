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
use SqlSemantics\Platform\MySql\Statement\Statement;
use Tests\Eris\TestTrait;
use Tests\Generator\MySql\Statement\Literal\TemporalLiteralGenerator;
use Tests\Generator\MySql\Statement\Routine\Condition\ConditionGenerator;
use Tests\Generator\MySql\Statement\Routine\Condition\SignalGenerator;

/**
 * Executes generated complete requests with invalid operands through the ordinary statement boundary.
 */
#[CoversNothing]
#[Large]
final class ConditionExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * @param 'signal'|'temporal'|'declaration' $family The generated SQL role
     */
    #[Test]
    #[DataProvider('providerRequests')]
    public function satisfiesGeneratedOperandsReachTheirSqlConditions(GrammarRelease $release, string $family): void
    {
        $requests = match ($family) {
            'signal' => SignalGenerator::inputConditions($release),
            'temporal' => TemporalLiteralGenerator::queries($release),
            'declaration' => ConditionGenerator::definitions($release),
        };
        $this->forAll($requests)->then(static function (array $sample) use ($release): void {
            [$statement, $code, $message] = $sample;
            self::assertInstanceOf(Statement::class, $statement);
            $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
            $session->text = 'Generated complete request without SQL input';
            try {
                (new Execution($session))->statement($statement);
                self::fail('The complete generated request retains its SQL condition.');
            } catch (SqlError $error) {
                self::assertSame($code, $error->getCode());
                self::assertSame($message, $error->getMessage());
            }
            $schema = $session->instance->dictionary->schema('pbt');
            self::assertNotNull($schema);
            self::assertSame([], $schema->procedures);
            self::assertSame([], $schema->functions);
            self::assertSame([], $schema->triggers);
            self::assertSame([], $schema->events);
            self::assertSame('Generated complete request without SQL input', $session->text);
            self::assertSame([], $session->transaction->statements->running);
            self::assertSame([], $session->running->using);
        });
    }

    /**
     * @return iterable<string, array{GrammarRelease, 'signal'|'temporal'|'declaration'}>
     */
    public static function providerRequests(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() === 'mysql') {
                foreach (['signal', 'temporal', 'declaration'] as $family) {
                    yield $release->value . ' ' . $family => [$release, $family];
                }
            }
        }
    }
}

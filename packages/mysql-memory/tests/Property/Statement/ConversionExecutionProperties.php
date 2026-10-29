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
use SqlSemantics\Statement\AnalyzedStatement;
use Tests\Eris\TestTrait;
use Tests\Generator\MySql\Statement\Expression\Conversion\CastGenerator;

/**
 * Executes complete conversion requests and their stored definition conditions.
 */
#[CoversNothing]
#[Large]
final class ConversionExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * Checks target bounds and definition conditions from the same operands.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesTargetConditionsPreserveOrdinaryAndUnexecutedStoredRequests(GrammarRelease $release): void
    {
        $this->forAll(CastGenerator::requests($release))->then(static function (array $input) use ($release): void {
            [$request, $definition, $code, $storedCode] = $input;
            self::assertInstanceOf(AnalyzedStatement::class, $request);
            self::assertInstanceOf(AnalyzedStatement::class, $definition);
            $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
            $session->text = 'generated conversion operands without SQL';
            try {
                (new Execution($session))->statement($request);
                self::fail('Target bounds precede ordinary operand resolution.');
            } catch (SqlError $error) {
                self::assertSame($code, $error->getCode());
            }
            try {
                (new Execution($session))->statement($definition);
                self::fail('The retained conversion condition prevents installation of the routine.');
            } catch (SqlError $error) {
                self::assertSame($storedCode ?? $code, $error->getCode());
            }
            $schema = $session->instance->dictionary->schema('pbt');
            self::assertNotNull($schema);
            self::assertSame([], $schema->procedures);
            self::assertSame([], $schema->functions);
            self::assertSame('generated conversion operands without SQL', $session->text);
            self::assertSame([], $session->transaction->statements->running);
            self::assertSame([], $session->running->using);
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

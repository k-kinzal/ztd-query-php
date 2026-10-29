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
use Tests\Generator\MySql\Statement\Server\Maintenance\AnalyzeTableGenerator;

/**
 * Executes complete maintenance lists and their stored definition conditions.
 */
#[CoversNothing]
#[Large]
final class MaintenanceExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * Checks duplicate targets and stored-statement restrictions from the same operands.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesRepeatedTargetsKeepOrdinaryAndStoredConditions(GrammarRelease $release, bool $selected): void
    {
        $this->forAll(AnalyzeTableGenerator::repeatedTargets($release, $selected))->then(static function (array $input) use ($release, $selected): void {
            [$request, $definition, $code, $ordinaryCode] = $input;
            self::assertInstanceOf(AnalyzedStatement::class, $request);
            self::assertInstanceOf(AnalyzedStatement::class, $definition);
            $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: $selected ? 'pbt' : null);
            $session->text = 'generated maintenance targets without SQL';
            try {
                (new Execution($session))->statement($request);
                self::fail('Repeated targets fail before table lookup.');
            } catch (SqlError $error) {
                self::assertSame($ordinaryCode, $error->getCode());
            }
            try {
                (new Execution($session))->statement($definition);
                self::fail('The retained maintenance condition prevents installation of the routine.');
            } catch (SqlError $error) {
                self::assertSame($code, $error->getCode());
            }
            $schema = $session->instance->dictionary->schema('pbt');
            self::assertNotNull($schema);
            self::assertSame([], $schema->procedures);
            self::assertSame([], $schema->functions);
            self::assertSame('generated maintenance targets without SQL', $session->text);
            self::assertSame([], $session->transaction->statements->running);
            self::assertSame([], $session->running->using);
        });
    }

    /**
     * @return iterable<string, array{GrammarRelease, bool}>
     */
    public static function providerReleases(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() === 'mysql') {
                yield $release->value . ' selected database' => [$release, true];
                yield $release->value . ' no database' => [$release, false];
            }
        }
    }
}

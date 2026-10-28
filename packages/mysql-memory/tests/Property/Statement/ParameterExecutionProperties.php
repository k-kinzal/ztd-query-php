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
use Tests\Generator\MySql\Statement\Routine\ParameterListGenerator;

/**
 * Executes complete generated routine definitions without any SQL input.
 */
#[CoversNothing]
#[Large]
final class ParameterExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * Checks parameter conditions before a complete SQL or external routine is installed.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesCompleteParametersKeepTheirDefinitionConditions(GrammarRelease $release): void
    {
        $this->forAll(ParameterListGenerator::definitions($release))->then(static function (array $input) use ($release): void {
            [$definition, $code, $message] = $input;
            self::assertInstanceOf(AnalyzedStatement::class, $definition);
            $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
            $session->text = 'generated routine parameters without SQL';
            try {
                (new Execution($session))->statement($definition);
                self::fail('Invalid parameter names prevent installation of the complete routine.');
            } catch (SqlError $error) {
                self::assertSame($code, $error->getCode());
                self::assertSame($message, $error->getMessage());
            }
            $schema = $session->instance->dictionary->schema('pbt');
            self::assertNotNull($schema);
            self::assertSame([], $schema->procedures);
            self::assertSame([], $schema->functions);
            self::assertSame('generated routine parameters without SQL', $session->text);
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

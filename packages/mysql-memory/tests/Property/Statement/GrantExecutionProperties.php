<?php

declare(strict_types=1);

namespace Tests\Property\Statement;

use Eris\TestTrait;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use Tests\Generator\MySql\Statement\Account\Privilege\GrantPrivilegesGenerator;

/**
 * Executes the same generated grant operands directly and through a stored invocation.
 */
#[CoversNothing]
#[Large]
final class GrantExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * Executes complete grant operands at their ordinary, definition and invocation boundaries.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesCompleteGrantsKeepDefinitionAndInvocationConditions(GrammarRelease $release): void
    {
        $this->forAll(GrantPrivilegesGenerator::conditions($release))->then(static function (array $input) use ($release): void {
            [$grant, $definition, $call, $code, $duringDefinition, $warnings] = $input;
            self::assertInstanceOf(\SqlSemantics\Statement\AnalyzedStatement::class, $grant);
            self::assertInstanceOf(\SqlSemantics\Statement\AnalyzedStatement::class, $definition);
            self::assertInstanceOf(\SqlSemantics\Statement\AnalyzedStatement::class, $call);
            $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
            $schema = $session->instance->dictionary->schema('pbt');
            self::assertNotNull($schema);
            $session->text = 'generated grant operands without SQL';
            $execution = new Execution($session);
            try {
                $execution->statement($grant);
                self::fail('The generated grant retains its target condition.');
            } catch (SqlError $error) {
                self::assertSame($code, $error->getCode());
            }
            self::assertSame($warnings, array_values(array_filter(array_column($session->diagnostics->conditions, 1), static fn (int $code): bool => $code === 1287)));
            if ($duringDefinition) {
                try {
                    $execution->statement($definition);
                    self::fail('Legacy column targets fail before the definition is installed.');
                } catch (SqlError $error) {
                    self::assertSame($code, $error->getCode());
                }
                self::assertSame([], $schema->procedures);
            } else {
                self::assertInstanceOf(Completion::class, $execution->statement($definition));
                try {
                    $execution->statement($call);
                    self::fail('The stored target condition is raised when the grant is reached.');
                } catch (SqlError $error) {
                    self::assertSame($code, $error->getCode());
                }
                self::assertCount(1, $schema->procedures);
            }
            self::assertSame('generated grant operands without SQL', $session->text);
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

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
use Tests\Generator\MySql\Statement\StatementGenerator;

/**
 * Executes retained account-name conditions through ordinary and stored request boundaries.
 */
#[CoversNothing]
#[Large]
final class AccountNameExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * A generated account condition prevents mutation before catalog lookup.
     */
    #[Test]
    #[DataProvider('providerStates')]
    public function satisfiesNameConditionsPrecedeCatalogChanges(GrammarRelease $release, ?string $database): void
    {
        $this->forAll(StatementGenerator::accountRequests($release))->then(static function (array $input) use ($release, $database): void {
            [$request, $code] = $input;
            self::assertInstanceOf(AnalyzedStatement::class, $request);
            $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: $database);
            $session->text = 'Generated account operands without SQL';
            $before = $session->instance->accounts->copy();
            try {
                (new Execution($session))->statement($request);
                self::fail('The name condition prevents executing the request.');
            } catch (SqlError $error) {
                self::assertSame($code, $error->getCode());
            }
            self::assertEquals($before, $session->instance->accounts);
            self::assertSame('Generated account operands without SQL', $session->text);
            self::assertSame([], $session->transaction->statements->running);
        });
    }

    /**
     * Stored instructions preserve the first condition even in an unreachable branch.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesStoredNameConditionsRespectInstructionOrder(GrammarRelease $release): void
    {
        $this->forAll(StatementGenerator::accountPrograms($release))->then(static function (array $input) use ($release): void {
            [$definition, $code] = $input;
            self::assertInstanceOf(AnalyzedStatement::class, $definition);
            $session = (new Instance(substr($release->value, 6), databases: ['pbt']))->connect(database: 'pbt');
            $session->text = 'Generated stored account operands without SQL';
            try {
                (new Execution($session))->statement($definition);
                self::fail('The first stored condition prevents installation.');
            } catch (SqlError $error) {
                self::assertSame($code, $error->getCode());
            }
            self::assertSame([], $session->instance->dictionary->schema('pbt')?->procedures);
            self::assertSame('Generated stored account operands without SQL', $session->text);
            self::assertSame([], $session->transaction->statements->running);
        });
    }

    /**
     * @return iterable<string, array{GrammarRelease, string|null}>
     */
    public static function providerStates(): iterable
    {
        foreach (self::providerReleases() as $name => [$release]) {
            yield $name . ' with database' => [$release, 'pbt'];
            yield $name . ' without database' => [$release, null];
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

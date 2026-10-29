<?php

declare(strict_types=1);

namespace Tests\Property\Statement;

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
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use Tests\Eris\TestTrait;
use Tests\Generator\MySql\Statement\StatementGenerator;

/**
 * Executes complete definition requests with retained option conditions and generated dependencies.
 */
#[CoversNothing]
#[Large]
final class DefinitionOptionsProperties extends TestCase
{
    use TestTrait;

    /**
     * Resolves generated options before target lookup and leaves existing definitions unchanged.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesOptionConditionsPrecedeInstallationAndTargetLookup(GrammarRelease $release): void
    {
        $this->forAll(StatementGenerator::definitionOptions($release))->then(static function (array $input) use ($release): void {
            [$create, $missing, $declared, $expected] = $input;
            self::assertInstanceOf(CreateTable::class, $create);
            self::assertInstanceOf(\SqlSemantics\Statement\AnalyzedStatement::class, $missing);
            self::assertInstanceOf(\SqlSemantics\Statement\AnalyzedStatement::class, $declared);
            $database = $create->name->schema->value ?? 'pbt';
            $session = (new Instance(substr($release->value, 6), [], [$database]))->connect(database: $database);
            $session->text = 'generated definition options without SQL';
            $execution = new Execution($session);
            try {
                $execution->statement($missing);
                self::fail('A definition option condition precedes missing target lookup.');
            } catch (SqlError $error) {
                self::assertSame($expected, $error->getCode());
            }
            self::assertSame([], $session->instance->dictionary->schema($database)?->tables);
            self::assertInstanceOf(Completion::class, $execution->statement($create));
            $tables = $session->instance->dictionary->schema($database)->tables;
            try {
                $execution->statement($declared);
                self::fail('A definition option condition precedes installing or altering its target.');
            } catch (SqlError $error) {
                self::assertSame($expected, $error->getCode());
            }
            self::assertSame($tables, $session->instance->dictionary->schema($database)->tables);
            self::assertSame('generated definition options without SQL', $session->text);
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

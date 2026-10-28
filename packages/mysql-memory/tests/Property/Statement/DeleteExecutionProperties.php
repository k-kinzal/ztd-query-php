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
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use Tests\Generator\MySql\Statement\Dml\DeleteGenerator;

/**
 * Executes complete generated deletions before and after their generated dependencies exist.
 */
#[CoversNothing]
#[Large]
final class DeleteExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * Resolves generated target and operand conditions using the published input bindings.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesCompleteDeletesUsePublishedBindings(GrammarRelease $release): void
    {
        $this->forAll(DeleteGenerator::constraints($release))->then(static function (array $input) use ($release): void {
            [$create, $missing, $declared, $before, $after, $explainedMissing, $explainedDeclared] = $input;
            self::assertInstanceOf(CreateTable::class, $create);
            $database = $create->name->schema->value ?? 'pbt';
            $session = (new Instance(substr($release->value, 6), [], [$database]))->connect(database: $database);
            $session->text = 'generated DELETE without SQL text';
            $execution = new Execution($session);
            foreach ([$missing, $explainedMissing] as $request) {
                self::assertInstanceOf(\SqlSemantics\Statement\AnalyzedStatement::class, $request);
                try {
                    $execution->statement($request);
                    self::fail('The complete deletion has a target or dependency condition.');
                } catch (SqlError $error) {
                    self::assertSame($before, $error->getCode());
                }
            }
            self::assertInstanceOf(Completion::class, $execution->statement($create));
            foreach ([$declared, $explainedDeclared] as $request) {
                self::assertInstanceOf(\SqlSemantics\Statement\AnalyzedStatement::class, $request);
                try {
                    $execution->statement($request);
                    self::fail('The complete deletion retains an operand condition.');
                } catch (SqlError $error) {
                    self::assertSame($after, $error->getCode());
                }
            }
            self::assertSame('generated DELETE without SQL text', $session->text);
            self::assertSame([], $session->transaction->statements->running);
            self::assertSame([], $session->running->using);
        });
    }


    /**
     * Deletes exactly the matching generated row through single and multiple-table forms.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesGeneratedDeletesChangeOnlyMatchingRows(GrammarRelease $release): void
    {
        $this->forAll(DeleteGenerator::rows($release))->then(static function (array $input) use ($release): void {
            [$create, $insert, $delete, $remaining] = $input;
            self::assertInstanceOf(CreateTable::class, $create);
            $database = $create->name->schema->value ?? 'pbt';
            $session = (new Instance(substr($release->value, 6), [], [$database]))->connect(database: $database);
            $session->text = 'generated dependent deletion without SQL text';
            $execution = new Execution($session);
            self::assertInstanceOf(Completion::class, $execution->statement($create));
            self::assertInstanceOf(\SqlSemantics\Statement\AnalyzedStatement::class, $insert);
            self::assertInstanceOf(\SqlSemantics\Statement\AnalyzedStatement::class, $delete);
            self::assertInstanceOf(Completion::class, $execution->statement($insert));
            $reply = $execution->statement($delete);
            self::assertInstanceOf(Completion::class, $reply);
            self::assertSame(1, $reply->affectedRows);
            self::assertSame(0, $reply->warnings);
            $table = $session->instance->dictionary->table($database, $create->name->name->value);
            self::assertNotNull($table);
            self::assertSame([[$remaining]], array_values($table->data->rows));
            self::assertSame('generated dependent deletion without SQL text', $session->text);
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

<?php

declare(strict_types=1);

namespace Tests\Property\Statement;

use Eris\TestTrait;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Statement\Operation;
use Tests\Generator\Semantics\Statement\OperationGenerator;

/**
 * Checks that MemoryDB consumes generated column-definition names through CREATE TABLE execution.
 */
#[CoversNothing]
#[Large]
final class CreateTableExecutionProperties extends TestCase
{
    use TestTrait;

    /**
     * A fresh table must contain the generated decoded name after the semantic operation executes.
     */
    #[Test]
    #[DataProvider('providerReleases')]
    public function satisfiesCreatedColumnRetainsItsName(GrammarRelease $release): void
    {
        $this->forAll(new OperationGenerator($release))->then(static function (Operation $operation) use ($release): void {
            $database = $operation->context->declarationSchema->value;
            $session = (new Instance(substr($release->value, 6), ['character_set_client' => 'utf8mb4', 'character_set_connection' => 'utf8mb4', 'collation_connection' => 'utf8mb4_unicode_ci'], [$database]))->connect(database: $database);

            $reply = (new Execution($session))->perform($operation, (new Dispatcher())->command($operation->statement));

            self::assertInstanceOf(Completion::class, $reply);
            self::assertInstanceOf(CreateTable::class, $operation->statement);
            $definition = $operation->statement->elements[0];
            self::assertInstanceOf(ColumnDefinition::class, $definition);
            $schema = $session->instance->dictionary->schema($database);
            self::assertNotNull($schema);
            $table = $schema->table($operation->statement->name->name->value);
            self::assertNotNull($table);
            self::assertCount(1, $table->definition->columns);
            self::assertSame($definition->name->column->value, $table->definition->columns[0]->name);
        });
    }

    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerReleases(): iterable
    {
        foreach ([GrammarRelease::MySql5651, GrammarRelease::MySql5744, GrammarRelease::MySql8044, GrammarRelease::MySql810, GrammarRelease::MySql820, GrammarRelease::MySql830, GrammarRelease::MySql847, GrammarRelease::MySql901, GrammarRelease::MySql910] as $release) {
            yield $release->value => [$release];
        }
    }
}

<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Name;

use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Operation;
use Tests\Generator\Choices;
use Tests\Generator\MySql\Statement\Name\ColumnNameGenerator;

/**
 * Checks that MemoryDB consumes generated column-definition names through CREATE TABLE execution.
 */
#[CoversNothing]
#[Large]
final class ColumnNameProperties extends TestCase
{
    /**
     * The semantic type exercised by this property.
     */
    public const MODEL = ColumnName::class;

    /**
     * A fresh table must contain the generated decoded name after the semantic operation executes.
     */
    #[Test]
    #[DataProvider('providerSeeds')]
    public function satisfiesCreatedColumnRetainsItsName(GrammarRelease $release, int $seed): void
    {
        $columnName = (new ColumnNameGenerator($release))->sample(new Choices('column-name:' . $seed));
        $tableName = $columnName->table ?? new QualifiedName(new Name('target'));
        $database = $tableName->schema ?? new Name('pbt');
        $statement = new CreateTable($tableName, [new ColumnDefinition($columnName, new OrdinaryColumn(new Integral(IntegralKind::Int)))]);
        $session = (new Instance(substr($release->value, 6), ['character_set_client' => 'utf8mb4', 'character_set_connection' => 'utf8mb4', 'collation_connection' => 'utf8mb4_unicode_ci'], [$database->value]))->connect(database: $database->value);
        $context = new AnalysisContext(new LanguageProfile($release), [$database], session: $session->resolution());
        $operation = new Operation($context, $statement);

        $reply = (new Execution($session))->perform($operation, (new Dispatcher())->command($statement));

        self::assertInstanceOf(Completion::class, $reply);
        $schema = $session->instance->dictionary->schema($database->value);
        self::assertNotNull($schema);
        $table = $schema->table($tableName->name->value);
        self::assertNotNull($table);
        self::assertCount(1, $table->definition->columns);
        self::assertSame($columnName->column->value, $table->definition->columns[0]->name);
    }

    /**
     * @return iterable<string, array{GrammarRelease, int}>
     */
    public static function providerSeeds(): iterable
    {
        foreach ([GrammarRelease::MySql5651, GrammarRelease::MySql5744, GrammarRelease::MySql8044, GrammarRelease::MySql810, GrammarRelease::MySql820, GrammarRelease::MySql830, GrammarRelease::MySql847, GrammarRelease::MySql901, GrammarRelease::MySql910] as $release) {
            for ($seed = 0; $seed < 100; $seed++) {
                yield $release->value . ':seed=' . $seed => [$release, $seed];
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace Tests\Integration\Statement;

use MySqlMemory\Instance;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;

#[CoversNothing]
#[Large]
final class InsertShrinkingTest extends TestCase
{
    public function testShrinkingPreservesTheGeneratedDestinationAndExecutesEveryCandidate(): void
    {
        $release = GrammarRelease::MySql847;
        $collation = \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_unicode_ci');
        $rows = \Tests\Generator\Operands::seq(new \Tests\Generator\MySql\Statement\Dml\Insert\InsertedRowGenerator(\Tests\Generator\Operands::vector(
            1,
            new \Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator($collation, \Eris\Generators::map(static fn (int $number): string => (string) $number, \Eris\Generators::int()))
        )));
        $sequences = \Eris\Generators::map(static function (array $data) use ($release): array {
            [$create, $rows] = $data;
            /** An INSERT needs a nonempty row list, including after shrinking. */
            $rows = $rows === [] ? [new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertedRow([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('0')])] : $rows;
            $database = $create->name->schema ?? new \SqlSemantics\Statement\Identifier\Name('pbt');
            $context = new \SqlSemantics\Contract\AnalysisContext(new \SqlSemantics\Contract\LanguageProfile($release), [$database], session: new \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_unicode_ci')));
            $publication = new \SqlSemantics\Platform\MySql\Construction\Publication();
            $definition = $publication->publish($context, $create);
            $column = $definition->declarations()[0]->columns[0];
            $insert = new InsertRows(new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto(false, null, false, new \SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget($create->name), new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\ColumnList([new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse($column->name)])), $rows);
            return [$definition, $publication->publish(new \SqlSemantics\Contract\AnalysisContext($context->profile, [$database], $definition->declarations(), session: $context->session), $insert)];
        }, \Tests\Generator\Operands::tuple(new \Tests\Generator\MySql\Statement\Table\CreateTableGenerator(new \Tests\Generator\MySql\Statement\Name\ColumnNameGenerator($release)), $rows));
        $random = new \Eris\Random\RandomRange(new \Eris\Random\MtRandSource());
        $random->seed(20261025);
        $quantifier = new \Eris\Quantifier\ForAll([$sequences], 100, new \Eris\Shrinker\ShrinkerFactory(['timeLimit' => null]), 'multiple', $random);
        $attempts = 0;
        $last = null;
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Intentional counterexample');
        try {
            $quantifier->withMaxSize(5)->then(static function (array $sequence) use (&$attempts, &$last): void {
                [$create, $insert] = $sequence;
                self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $create);
                self::assertInstanceOf(InsertRows::class, $insert);
                self::assertInstanceOf(\SqlSemantics\Statement\Reference\Table\DeclaredTable::class, $insert->into->table->relationInformation()->table);
                self::assertNotNull($insert->into->columns);
                self::assertArrayHasKey(0, $insert->into->columns->columns);
                $declaration = $create->declarations()[0];
                self::assertSame($declaration, $insert->into->table->relationInformation()->table->table);
                self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Name\ColumnUse::class, $insert->into->columns->columns[0]);
                $resolution = $insert->into->columns->columns[0]->scalarInformation()->resolution;
                self::assertInstanceOf(\SqlSemantics\Statement\Reference\Column\ResolvedColumn::class, $resolution);
                self::assertSame($declaration->columns[0], $resolution->declaration());
                $database = $insert->into->table->relationInformation()->table->name->schema->value ?? 'pbt';
                $session = (new Instance('8.4.7', ['character_set_client' => 'utf8mb4', 'character_set_connection' => 'utf8mb4', 'collation_connection' => 'utf8mb4_unicode_ci'], [$database]))->connect(database: $database);
                $execution = new Execution($session);
                $execution->statement($create);
                $execution->statement($insert);
                $stored = $session->instance->dictionary->table($database, $insert->into->table->name->name->value);
                self::assertNotNull($stored);
                $rows = array_values($stored->data->rows);
                if (count($rows) > 1) {
                    $last = $rows;
                }
                $attempts++;
                self::assertLessThanOrEqual(1, count($rows), 'Intentional counterexample: an INSERT can write multiple rows.');
            });
        } finally {
            self::assertGreaterThan(1, $attempts);
            self::assertSame([[0], [0]], $last);
        }
    }
}

<?php

declare(strict_types=1);

namespace Tests\Integration\Statement;

use LogicException;
use MySqlMemory\Instance;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertStatement;

#[CoversNothing]
#[Large]
final class InsertShrinkingTest extends TestCase
{
    public function testShrinkingPreservesTheGeneratedDestinationAndExecutesEveryCandidate(): void
    {
        $release = GrammarRelease::MySql847;
        $collation = \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_unicode_ci');
        $sequences = \Eris\Generators::map(static function (array $data) use ($release): array {
            $create = $data['create'];
            $values = $data['values'];
            if (!$create instanceof \SqlSemantics\Statement\Operation || !$values instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Insert\ValuesClause) {
                throw new LogicException('The sequence requires a generated CREATE TABLE and VALUES clause.');
            }
            $table = $create->declarations()[0];
            $resolution = new \SqlSemantics\Statement\Reference\Table\DeclaredTable($table);
            $column = $table->columns[0];
            $target = new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertTarget($table->name, $resolution, [new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\DestinationColumn($column->name, $resolution, $column)]);
            return [$create, new InsertStatement(new \SqlSemantics\Contract\LanguageProfile($release), $target, $values, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::known('utf8mb4'))];
        }, \Eris\Generators::associative([
            'create' => new \Tests\Generator\Semantics\Statement\OperationGenerator($release),
            'values' => new \Tests\Generator\MySql\Statement\Dml\Insert\ValuesClauseGenerator(new \Tests\Generator\Semantics\Statement\ExpressionGenerator($collation, \Eris\Generators::map(static fn (int $number): string => (string) $number, \Eris\Generators::int()))),
        ]));
        $random = new \Eris\Random\RandomRange(new \Eris\Random\MtRandSource());
        $random->seed(20261025);
        $quantifier = new \Eris\Quantifier\ForAll([$sequences], 100, new \Eris\Shrinker\ShrinkerFactory(['timeLimit' => null]), 'multiple', $random);
        $attempts = 0;
        $last = null;
        try {
            $quantifier->withMaxSize(5)->then(static function (array $sequence) use (&$attempts, &$last): void {
                [$create, $insert] = $sequence;
                self::assertInstanceOf(\SqlSemantics\Statement\Operation::class, $create);
                self::assertInstanceOf(InsertStatement::class, $insert);
                self::assertInstanceOf(\SqlSemantics\Statement\Reference\Table\DeclaredTable::class, $insert->target->resolution);
                self::assertNotNull($insert->target->columns);
                self::assertArrayHasKey(0, $insert->target->columns);
                $declaration = $create->declarations()[0];
                self::assertSame($declaration, $insert->target->resolution->table);
                self::assertSame($declaration->columns[0], $insert->target->columns[0]->declaration);
                $database = $create->context->declarationSchema->value;
                $session = (new Instance('8.4.7', ['character_set_client' => 'utf8mb4', 'character_set_connection' => 'utf8mb4', 'collation_connection' => 'utf8mb4_unicode_ci'], [$database]))->connect(database: $database);
                $execution = new Execution($session);
                $execution->statement($create);
                $execution->statement($insert);
                $stored = $session->instance->dictionary->table($database, $insert->target->name->name->value);
                self::assertNotNull($stored);
                $rows = array_values($stored->data->rows);
                if (count($rows) > 1) {
                    $last = $rows;
                }
                $attempts++;
                self::assertLessThanOrEqual(1, count($rows), 'Intentional counterexample: an INSERT can write multiple rows.');
            });
        } catch (AssertionFailedError $error) {
            self::assertStringContainsString('Intentional counterexample', $error->getMessage());
            self::assertGreaterThan(1, $attempts);
            self::assertSame([[0], [0]], $last);
            return;
        }
        self::fail('Eris must discover the intentionally false execution assertion.');
    }
}

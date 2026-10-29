<?php

declare(strict_types=1);

namespace Tests\Integration;

use Eris\Random\MersenneTwister;
use Eris\Random\RandomRange;
use LogicException;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use Tests\Generator\MySql\Statement\QueryRootGenerator;

#[CoversNothing]
#[Medium]
final class GeneratedDependencyShrinkingTest extends TestCase
{
    #[DataProvider('providerShrunkSequences')]
    public function testShrinkingRebuildsBindingsAndKeepsTheSequenceExecutable(CreateTable $create, InsertRows $insert, QueryRoot $query): void
    {
        $declaration = $create->declarations()[0];
        $written = $insert->into->table->relationInformation()->table;
        self::assertInstanceOf(DeclaredTable::class, $written);
        self::assertSame($declaration, $written->table);
        $resolution = $query->field(0)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        $read = $resolution->relation->relationInformation()->table;
        self::assertInstanceOf(DeclaredTable::class, $read);
        self::assertSame($declaration, $read->table);
        $execution = new Execution((new Instance('8.4.7', databases: ['pbt']))->connect(database: 'pbt'));
        $execution->statement($create);
        $execution->statement($insert);
        $reply = $execution->statement($query);
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertCount(count($insert->rows), $reply->rows);
        self::assertCount(count($declaration->columns), $reply->columns);
    }

    /**
     * @param list<string> $candidates
     */
    #[DataProvider('providerShrinkingDimensions')]
    public function testNativeShrinkingReachesBothPrerequisitesAndDependentOperands(array $candidates, string $original): void
    {
        self::assertNotEmpty(array_diff($candidates, [$original]));
    }

    /**
     * @return iterable<array{CreateTable, InsertRows, QueryRoot}>
     */
    public static function providerShrunkSequences(): iterable
    {
        $random = new RandomRange(new MersenneTwister());
        $random->seed(1983);
        $generator = QueryRootGenerator::withPrerequisites(GrammarRelease::MySql847);
        $original = $generator(8, $random);
        yield $original->unbox();
        foreach ($generator->shrink($original) as $candidate) {
            yield $candidate->unbox();
        }
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function providerShrinkingDimensions(): iterable
    {
        $random = new RandomRange(new MersenneTwister());
        $random->seed(1983);
        $generator = QueryRootGenerator::withPrerequisites(GrammarRelease::MySql847);
        $original = $generator(8, $random);
        $names = [];
        $widths = [];
        $counts = [];
        $values = [];
        $number = static function (\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand $value): string {
            if ($value instanceof \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral) {
                return $value->text;
            }
            if ($value instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary && $value->operand instanceof \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral) {
                return '-' . $value->operand->text;
            }
            throw new LogicException('The dependent integer generator produced a non-integer operand.');
        };
        foreach ($generator->shrink($original) as $candidate) {
            [$table, $insert] = $candidate->unbox();
            $names[] = $table->name->name->value;
            $widths[] = (string) count($table->declarations()[0]->columns);
            $counts[] = (string) count($insert->rows);
            $values[] = $number($insert->rows[0]->values[0]);
        }
        [$table, $insert] = $original->unbox();
        yield 'Table name' => [$names, $table->name->name->value];
        yield 'Column count' => [$widths, (string) count($table->declarations()[0]->columns)];
        yield 'Row count' => [$counts, (string) count($insert->rows)];
        yield 'Cell value' => [$values, $number($insert->rows[0]->values[0])];
    }
}

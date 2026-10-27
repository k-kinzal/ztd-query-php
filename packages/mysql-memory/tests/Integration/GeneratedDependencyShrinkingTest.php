<?php

declare(strict_types=1);

namespace Tests\Integration;

use Eris\Random\MersenneTwister;
use Eris\Random\RandomRange;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use Tests\Generator\MySql\Statement\QueryRootGenerator;

#[CoversNothing]
#[Medium]
final class GeneratedDependencyShrinkingTest extends TestCase
{
    /**
     * @param array{\SqlSemantics\Platform\MySql\Statement\Table\CreateTable, \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows, \SqlSemantics\Platform\MySql\Statement\QueryRoot} $sequence
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerShrunkSequences')]
    public function testShrinkingKeepsGeneratedWritesAndReadsBoundToTheShrunkDeclaration(array $sequence): void
    {
        [$create, $insert, $query] = $sequence;
        $declaration = $create->declarations()[0];
        $written = $insert->into->table->relationInformation()->table;
        self::assertInstanceOf(DeclaredTable::class, $written);
        self::assertSame($declaration, $written->table);
        $resolution = $query->field(0)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        $read = $resolution->relation->relationInformation()->table;
        self::assertInstanceOf(DeclaredTable::class, $read);
        self::assertSame($declaration, $read->table);
        $position = array_search($resolution->declaration(), [...$declaration->columns, ...array_map(static fn ($hidden) => $hidden->column, $declaration->implicit)], true);
        self::assertIsInt($position);
        $values = array_map(static function ($value): string {
            self::assertInstanceOf(NumberLiteral::class, $value);
            return $value->text;
        }, $insert->rows[0]->values);
        $values[] = null;
        $execution = new Execution((new Instance('8.4.7', databases: ['pbt']))->connect(database: 'pbt'));
        $execution->statement($create);
        $execution->statement($insert);
        $reply = $execution->statement($query);
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([[$values[$position]]], $reply->rows);
    }

    /**
     * @param list<string> $candidates
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerShrinkingDimensions')]
    public function testShrinkingReachesPrerequisiteNamesAndDependentValues(array $candidates, string $original): void
    {
        self::assertNotEmpty(array_diff($candidates, [$original]));
    }

    /**
     * @return iterable<array{array{\SqlSemantics\Platform\MySql\Statement\Table\CreateTable, \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows, \SqlSemantics\Platform\MySql\Statement\QueryRoot}}>
     */
    public static function providerShrunkSequences(): iterable
    {
        $random = new RandomRange(new MersenneTwister());
        $random->seed(1907);
        $generator = QueryRootGenerator::declaredTableSequence();
        foreach ($generator->shrink($generator(100, $random)) as $candidate) {
            yield [$candidate->unbox()];
        }
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function providerShrinkingDimensions(): iterable
    {
        $random = new RandomRange(new MersenneTwister());
        $random->seed(1907);
        $generator = QueryRootGenerator::declaredTableSequence();
        $original = $generator(100, $random);
        $names = [];
        $values = [];
        foreach ($generator->shrink($original) as $candidate) {
            $sequence = $candidate->unbox();
            $names[] = $sequence[0]->name->name->value;
            $values[] = serialize(array_map(static fn ($row): array => array_map(static fn ($value): string => $value instanceof NumberLiteral ? $value->text : '', $row->values), $sequence[1]->rows));
        }
        $before = $original->unbox();
        yield 'Prerequisite table names' => [$names, $before[0]->name->name->value];
        yield 'Dependent inserted values' => [$values, serialize(array_map(static fn ($row): array => array_map(static fn ($value): string => $value instanceof NumberLiteral ? $value->text : '', $row->values), $before[1]->rows))];
    }
}

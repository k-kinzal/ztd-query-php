<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Table;

use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Table\Columns;
use Fuzz\Structure\Audit\Table\History;
use Fuzz\Structure\Choices;
use Fuzz\Structure\Execution;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Populated relation fixtures preserve scalar namespaces and subquery widths.
 */
#[CoversNothing]
#[Medium]
final class ColumnsTest extends TestCase
{
    /**
     * @return iterable<int, array{int}>
     */
    public static function providerSeeds(): iterable
    {
        foreach (range(0, 29) as $seed) {
            yield [$seed];
        }
    }

    /**
     * Executes the same generated predicate after establishing both missing relations.
     */
    #[DataProvider('providerSeeds')]
    public function testNamesKeepAmbientIdentitiesAndTheConsumedTableWidth(int $seed): void
    {
        $choices = new Choices('table-column-width:' . $seed);
        $width = $choices->integer(1, 4);
        $names = array_map(static fn (int $index): Name => new Name('c' . $index . '_' . $seed), range(1, max(2, $width)));
        $uses = array_map(static fn (Name $name): M\Name\ColumnUse => new M\Name\ColumnUse($name), $names);
        $operand = $width === 1 ? new M\Expression\Operator\Arithmetic(M\Expression\Operator\ArithmeticOperator::Plus, $uses[0], $uses[1]) : new M\Expression\Row($uses);
        $table = new QualifiedName(new Name('t.' . $seed));
        $query = new M\Query\ParenthesizedQuery(new M\Query\ExplicitTable($table));
        $predicate = new M\Expression\Subquery\InQuery($operand, $query);
        $columns = new Columns();
        self::assertSame($names, $columns->names($predicate, new QualifiedName(new Name('input_0'), new Name('structure_fixture')), 'd'));
        self::assertCount($width, $columns->names($predicate, new QualifiedName(new Name('t.' . $seed), new Name('d')), 'd'));
        $session = (new Instance('8.4.7', [], ['d', 'structure_fixture']))->connect(database: 'd');
        $session->text = 'unrelated invalid input';
        foreach (range(0, 1) as $step) {
            $before = (new Memory())->observe($session, $predicate);
            self::assertSame(1146, $before['error'][0] ?? null);
            $history = (new History())->steps($predicate, $before, 'd');
            self::assertCount(3, $history);
            foreach ($history as $instruction) {
                (new Execution())->runIn($session, $instruction);
            }
        }
        $after = (new Memory())->observe($session, $predicate);
        self::assertArrayNotHasKey('error', $after);
        self::assertSame([[$width === 1 ? '0' : '1']], $after['results'][0]['rows']);
        self::assertSame($operand, $predicate->operand);
        self::assertSame($query, $predicate->query);
        self::assertSame('unrelated invalid input', $session->text);
    }

    /**
     * Distinguishes scalar and row consumers without merging unrelated table names.
     */
    public function testWidthUsesTheSubqueryRoleAndKeepsUnrelatedTablesSeparate(): void
    {
        $name = new QualifiedName(new Name('t'));
        $query = new M\Query\ExplicitTable($name);
        $scalar = new M\Expression\Subquery\ScalarSubquery($query);
        $row = new M\Expression\Row([new M\Literal\NumberLiteral('7'), new M\Literal\NumberLiteral('7')]);
        $predicate = new M\Expression\Subquery\QuantifiedComparison($row, M\Expression\ComparisonOperator::Equal, M\Expression\Subquery\Quantifier::Any, $query);
        $columns = new Columns();
        self::assertSame(1, $columns->width($scalar, $name, 'd'));
        self::assertSame(2, $columns->width($predicate, $name, 'd'));
        self::assertNull($columns->width($predicate, new QualifiedName(new Name('other')), 'd'));
        self::assertFalse($columns->same($name, new QualifiedName(new Name('t'), new Name('other')), 'd'));
        self::assertFalse($columns->same($name, new QualifiedName(new Name('T')), 'd'));
    }

    /**
     * Keeps key-part references as fixture column identities before index creation.
     */
    public function testNamesRetainsColumnsReferencedByAnIndex(): void
    {
        $column = new Name('key.column');
        $table = new QualifiedName(new Name('t'), new Name('d'));
        $index = new M\Table\CreateIndex(new Name('idx'), $table, [new M\Table\Key\ExpressionPart(new M\Literal\NumberLiteral('1')), new M\Table\Key\ColumnPart($column)]);
        self::assertSame([$column], (new Columns())->names($index, $table, 'd'));
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->text = 'unrelated invalid input';
        $before = (new Memory())->observe($session, $index);
        $history = (new History())->steps($index, $before, 'd');
        self::assertCount(3, $history);
        foreach ($history as $instruction) {
            (new Execution())->runIn($session, $instruction);
        }
        self::assertArrayNotHasKey('error', (new Memory())->observe($session, $index));
        self::assertInstanceOf(M\Table\Key\ColumnPart::class, $index->parts[1]);
        self::assertSame($column, $index->parts[1]->column);
        self::assertSame('unrelated invalid input', $session->text);
    }
}

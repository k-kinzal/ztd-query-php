<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Table;

use Fuzz\Structure\Audit\Table\FullTextHistory;
use Fuzz\Structure\Audit\Table\History;
use Fuzz\Structure\Choices;
use Fuzz\Structure\Execution;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;

/**
 * The generated MATCH operand survives the catalog transition unchanged.
 */
#[CoversNothing]
#[Small]
final class FullTextHistoryTest extends TestCase
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
     * Executes a populated text relation after replacing the initial numeric fixture.
     */
    #[DataProvider('providerSeeds')]
    public function testStepsPreserveGeneratedColumnsAndTheOriginalExpression(int $seed): void
    {
        $choices = new Choices('fulltext-history:' . $seed);
        $columns = array_map(static fn (int $index): M\Name\ColumnUse => new M\Name\ColumnUse(new Name('column.' . $seed . '.' . $index)), range(0, $choices->integer(0, 3)));
        $node = new M\Expression\Access\FullTextSearch($columns, new M\Literal\StringLiteral(['missing']));
        $before = ['error' => [1146, '42S02', "Table 'structure_fixture.input_0' doesn't exist"]];
        $session = (new Instance())->connect();
        $session->text = 'unrelated invalid input';
        $execution = new Execution();
        foreach ((new History())->steps($node, $before, '') as $step) {
            $execution->runIn($session, $step);
        }
        $steps = (new FullTextHistory())->steps($node, 1191);
        self::assertCount(3, $steps);
        foreach ($steps as $step) {
            $execution->runIn($session, $step);
        }
        $result = $execution->runIn($session, $node);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0']], $result->rows);
        self::assertSame($columns, $node->columns);
        self::assertSame('unrelated invalid input', $session->text);
        self::assertSame([], (new FullTextHistory())->steps($node, 1064));
    }

    /**
     * Finds a nested MATCH without assigning the same scope to an inner query.
     */
    public function testSearchesKeepsNestedOperandsInTheirOwnNamespace(): void
    {
        $match = new M\Expression\Access\FullTextSearch([new M\Name\ColumnUse(new Name('a'))], new M\Literal\NullLiteral());
        $inner = new M\Expression\Access\FullTextSearch([new M\Name\ColumnUse(new Name('b'))], new M\Literal\NullLiteral());
        $query = new M\Query\Select([], [new M\Query\SelectExpression($inner)]);
        $outer = new M\Expression\Subquery\InQuery($match, $query);
        self::assertSame([$match], (new FullTextHistory())->searches($outer));
        self::assertSame([], (new FullTextHistory())->searches(new M\Expression\Subquery\ScalarSubquery($query)));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Table;

use Fuzz\Structure\Audit\Table\History;
use Fuzz\Structure\Audit\Table\Inputs;
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
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Populated fixture construction preserves generated names and the tested object.
 */
#[CoversNothing]
#[Small]
final class HistoryTest extends TestCase
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
     * Establishes each generated identity and reads its populated row without replacing the query.
     */
    #[DataProvider('providerSeeds')]
    public function testStepsSupplyRowsForTheSameGeneratedQuery(int $seed): void
    {
        $choices = new Choices('table-history:' . $seed);
        $schema = new Name($choices->one(['d.', '']) . $choices->integer(0, 1000));
        $name = new QualifiedName(new Name($choices->one(['t.', '']) . $choices->integer(0, 1000)), $schema);
        $column = new Name('c' . $choices->integer(0, 1000));
        $query = new M\QueryRoot(new M\Query\Select([], [new M\Query\SelectExpression(new M\Name\ColumnUse($column))], new M\Relation\TableReference($name)));
        $message = "Table '" . $schema->value . '.' . $name->name->value . "' doesn't exist";
        $before = ['error' => [1146, '42S02', $message]];
        $session = (new Instance())->connect();
        $session->text = 'unrelated invalid input';
        $execution = new Execution();
        $history = (new History())->steps($query, $before, '');
        self::assertCount(3, $history);
        foreach ($history as $step) {
            $execution->runIn($session, $step);
        }
        $result = $execution->runIn($session, $query);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['7']], $result->rows);
        self::assertSame($column, (new Inputs())->columns($query)[0]);
        self::assertSame($query, (new Inputs())->values($query)[0]);
        self::assertSame($name->name, (new Inputs())->missing($query, $message, '')?->name);
        self::assertSame('unrelated invalid input', $session->text);
    }

    /**
     * Leaves observations without an identified missing relation unresolved.
     */
    public function testStepsKeepUnknownIdentitiesAndUnrelatedErrorsUnresolved(): void
    {
        $query = new M\QueryRoot(new M\Query\ExplicitTable(new QualifiedName(new Name('t'))));
        self::assertSame([], (new History())->steps($query, ['error' => [1146, '42S02', "Table 'd.u' doesn't exist"]], 'd'));
        self::assertSame([], (new History())->steps($query, ['error' => [1054, '42S22', 'Unknown column']], 'd'));
        self::assertSame([], (new History())->steps($query, ['results' => []], 'd'));
        self::assertSame('fixture_value', (new Inputs())->columns($query)[0]->value);
    }
}

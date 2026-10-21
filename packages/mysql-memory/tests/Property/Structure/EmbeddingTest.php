<?php

declare(strict_types=1);

namespace Tests\Property\Structure;

use Fuzz\Structure\Embedding;
use Fuzz\Structure\Execution;
use Fuzz\Structure\Reference;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Checks that free scalar context is identical for direct and native execution.
 */
#[CoversNothing]
#[Small]
final class EmbeddingTest extends TestCase
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
     * Supplies populated state for each generated free column while retaining its identity.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSeeds')]
    public function testStatementKeepsFreeScalarIdentityAcrossPopulatedBindings(int $seed): void
    {
        $choices = new \Fuzz\Structure\Choices('ambient-columns:' . $seed);
        $name = new Name('c' . $choices->integer(0, 1000));
        $qualifier = $choices->one([null, new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('a.b')), new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('t'), new Name('d'))]);
        $scalar = new M\Name\ColumnUse($name, $qualifier);
        $statement = (new Embedding())->statement($scalar);
        self::assertInstanceOf(M\QueryRoot::class, $statement);
        self::assertInstanceOf(M\Query\Select::class, $statement->query);
        self::assertInstanceOf(M\Query\SelectExpression::class, $statement->query->items[0]);
        self::assertSame($scalar, $statement->query->items[0]->expression);
        $binding = (new \Fuzz\Structure\Context\Columns())->bindings($scalar)[0];
        $table = $binding['relation']->name;
        $before = ['error' => [1146, '42S02', "Table '" . $table->schema?->value . '.' . $table->name->value . "' doesn't exist"]];
        $session = (new \MySqlMemory\Instance())->connect();
        $session->text = 'unrelated invalid input';
        $execution = new Execution();
        foreach ((new \Fuzz\Structure\Audit\Table\History())->steps($scalar, $before, '') as $step) {
            $execution->runIn($session, $step);
        }
        $result = $execution->runIn($session, $scalar);
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result);
        self::assertSame([['7']], $result->rows);
        self::assertSame('unrelated invalid input', $session->text);
    }

    /**
     * Nested queries keep their own namespace and are not supplied with outer relations.
     */
    public function testStatementLeavesSubqueryColumnScopeUnchanged(): void
    {
        $query = new M\Query\Select([], [new M\Query\SelectExpression(new M\Name\ColumnUse(new Name('missing')))]);
        $scalar = new M\Expression\Subquery\ScalarSubquery($query);
        self::assertSame([], (new \Fuzz\Structure\Context\Columns())->bindings($scalar));
        $statement = (new Embedding())->statement($scalar);
        self::assertInstanceOf(M\QueryRoot::class, $statement);
        self::assertInstanceOf(M\Query\Select::class, $statement->query);
        self::assertNull($statement->query->from);
    }

    /**
     * Supplies a lexical window while retaining the original scalar by identity.
     */
    public function testStatementSuppliesTheAmbientWindowWithoutReplacingTheOriginalScalar(): void
    {
        $call = new M\Call\Window\WindowFunction(M\Call\Window\WindowFunctionKind::RowNumber, [], new Name('w'));
        $statement = (new Embedding())->statement($call);
        self::assertInstanceOf(M\QueryRoot::class, $statement);
        self::assertInstanceOf(M\Query\Select::class, $statement->query);
        self::assertInstanceOf(M\Query\SelectExpression::class, $statement->query->items[0]);
        self::assertSame($call, $statement->query->items[0]->expression);
        self::assertSame('w', $statement->query->windows[0]->name->value);
        self::assertSame($statement, (new Embedding())->statement($statement));
        $result = (new Execution())->run($call);
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
        $profile = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->profile();
        self::assertSame(\SqlSemantics\Contract\GrammarRelease::MySql8044, (new \Fuzz\Structure\Profiles())->select($call)->grammar);
        self::assertSame('SELECT ROW_NUMBER() OVER w WINDOW w AS ()', (new Reference())->sql($call, $profile));
    }
    /**
     * An empty output alias remains valid without trying to create an empty stored-column name.
     */
    public function testStatementKeepsAnEmptyDefaultColumnAliasAndUsesOrdinaryStorageNames(): void
    {
        $column = new M\Name\ColumnUse(new Name(''));
        $scalar = new M\Expression\Access\DefaultOfColumn($column);
        $statement = (new Embedding())->statement($scalar);
        self::assertInstanceOf(M\QueryRoot::class, $statement);
        self::assertInstanceOf(M\Query\Select::class, $statement->query);
        self::assertInstanceOf(M\Query\SelectExpression::class, $statement->query->items[0]);
        self::assertSame($scalar, $statement->query->items[0]->expression);
        $binding = (new \Fuzz\Structure\Context\Columns())->bindings($scalar)[0];
        self::assertSame('', $binding['names'][0]->value);
        self::assertSame('fixture_column_0', $binding['columns'][0]->value);
        $session = (new \MySqlMemory\Instance())->connect();
        $session->query('CREATE DATABASE structure_fixture; CREATE TABLE structure_fixture.input_0(fixture_column_0 INT DEFAULT 7); INSERT INTO structure_fixture.input_0 VALUES (3)');
        $session->text = 'opaque input';
        $result = (new Execution())->runIn($session, $scalar);
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result);
        self::assertSame([['7']], $result->rows);
        self::assertSame('opaque input', $session->text);
    }

}

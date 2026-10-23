<?php

declare(strict_types=1);

namespace Tests\Property\Structure;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Fake\MemoryClient as Execution;
use Tests\Generator\Embedding;

/**
 * Checks that embedding preserves scalar identity and supplies its required context.
 */
#[CoversNothing]
#[Small]
final class EmbeddingProperties extends TestCase
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
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesStatementKeepsFreeScalarIdentityAcrossPopulatedBindings(int $seed): void
    {
        $choices = new \Tests\Generator\Choices('ambient-columns:' . $seed);
        $name = new Name('c' . $choices->integer(0, 1000));
        $qualifier = $choices->one([null, new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('a.b')), new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('t'), new Name('d'))]);
        $scalar = new M\Name\ColumnUse($name, $qualifier);
        $statement = (new Embedding())->statement($scalar);
        self::assertInstanceOf(M\QueryRoot::class, $statement);
        self::assertInstanceOf(M\Query\Select::class, $statement->query);
        self::assertInstanceOf(M\Query\SelectExpression::class, $statement->query->items[0]);
        self::assertSame($scalar, $statement->query->items[0]->expression);
        $binding = (new \Tests\Generator\Context\Columns())->bindings($scalar)[0];
        $table = $binding['relation']->name;
        $schema = $table->schema->value ?? 'structure_fixture';
        $session = (new \MySqlMemory\Instance(databases: [$schema]))->connect(database: $schema);
        $identifier = '`' . str_replace('`', '``', $table->name->value) . '`';
        $session->query('CREATE TABLE ' . $identifier . ' (`' . $name->value . '` INT); INSERT INTO ' . $identifier . ' VALUES (7)');
        $session->text = 'unrelated invalid input';
        $execution = new Execution();
        $result = $execution->runIn($session, $scalar);
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result);
        self::assertSame([['7']], $result->rows);
        self::assertSame('unrelated invalid input', $session->text);
    }

    /**
     * Nested queries keep their own namespace and are not supplied with outer relations.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesStatementLeavesSubqueryColumnScopeUnchanged(): void
    {
        $query = new M\Query\Select([], [new M\Query\SelectExpression(new M\Name\ColumnUse(new Name('missing')))]);
        $scalar = new M\Expression\Subquery\ScalarSubquery($query);
        self::assertSame([], (new \Tests\Generator\Context\Columns())->bindings($scalar));
        $statement = (new Embedding())->statement($scalar);
        self::assertInstanceOf(M\QueryRoot::class, $statement);
        self::assertInstanceOf(M\Query\Select::class, $statement->query);
        self::assertNull($statement->query->from);
    }

    /**
     * Supplies a lexical window while retaining the original scalar by identity.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesStatementSuppliesTheAmbientWindowWithoutReplacingTheOriginalScalar(): void
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
        self::assertSame(\SqlSemantics\Contract\GrammarRelease::MySql8044, (new \Tests\Generator\Profiles())->select($call)->grammar);
    }
    /**
     * An empty output alias remains valid without trying to create an empty stored-column name.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesStatementKeepsAnEmptyDefaultColumnAliasAndUsesOrdinaryStorageNames(): void
    {
        $column = new M\Name\ColumnUse(new Name(''));
        $scalar = new M\Expression\Access\DefaultOfColumn($column);
        $statement = (new Embedding())->statement($scalar);
        self::assertInstanceOf(M\QueryRoot::class, $statement);
        self::assertInstanceOf(M\Query\Select::class, $statement->query);
        self::assertInstanceOf(M\Query\SelectExpression::class, $statement->query->items[0]);
        self::assertSame($scalar, $statement->query->items[0]->expression);
        $binding = (new \Tests\Generator\Context\Columns())->bindings($scalar)[0];
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

<?php

declare(strict_types=1);

namespace Tests\Unit\Facade;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Analysis\ModelGraph;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect as MySql;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSql;
use SqlSemantics\Platform\Sqlite\Dialect as Sqlite;
use SqlSemantics\Semantic\Expression\ColumnReference;
use SqlSemantics\Semantic\Name;
use SqlSemantics\Semantic\Projection\Field;
use SqlSemantics\Semantic\QualifiedName;
use SqlSemantics\Semantic\Reference\CandidateColumn;
use SqlSemantics\Semantic\Reference\MissingColumn;
use SqlSemantics\Semantic\Reference\ResolvedColumn;
use SqlSemantics\Semantic\Statement\Select;
use Tests\Scenario\SemanticCases;

#[CoversClass(Semantics::class)]
#[Medium]
final class SemanticsTest extends TestCase
{
    #[TestWith([MySql::MySql])]
    #[TestWith([PostgreSql::PostgreSql])]
    #[TestWith([Sqlite::Sqlite])]
    public function testAnalyzeRefinesMissingCatalogFactsIntoDeclarationReferences(Dialect $dialect): void
    {
        $semantics = new Semantics($dialect);
        $open = $semantics->analyze('SELECT foo FROM bar');
        self::assertInstanceOf(Select::class, $open);
        self::assertInstanceOf(ColumnReference::class, $open->field('foo')->expression);
        self::assertInstanceOf(CandidateColumn::class, $open->field('foo')->expression->binding);
        self::assertSame('unknown', $open->field('foo')->type->name);

        $table = SemanticCases::table($dialect);
        $closed = $semantics->analyze('SELECT foo FROM bar', [$table]);
        self::assertInstanceOf(Select::class, $closed);
        self::assertInstanceOf(ColumnReference::class, $closed->field('foo')->expression);
        self::assertInstanceOf(ResolvedColumn::class, $closed->field('foo')->expression->binding);
        self::assertSame($table, $closed->tables[0]->declaration);
        self::assertSame($table->columns[0], $closed->field('foo')->expression->binding->column);
        self::assertSame('integer', $closed->field('foo')->type->name);
        self::assertSame('SELECT foo FROM bar', $closed->toString());

        $empty = $semantics->analyze('SELECT foo FROM bar', []);
        self::assertInstanceOf(Select::class, $empty);
        self::assertInstanceOf(ColumnReference::class, $empty->field('foo')->expression);
        self::assertInstanceOf(MissingColumn::class, $empty->field('foo')->expression->binding);
        self::assertSame('unknown', $open->field('foo')->type->name);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerProjectionCases')]
    public function testProjectionEditsAgreeWithIndependentDatabaseQueries(string $alias, string $expression, bool $filtered, bool $ordered): void
    {
        $database = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $database->exec('CREATE TABLE bar (foo INTEGER NOT NULL, label TEXT)');
        $database->exec("INSERT INTO bar VALUES (1, 'one'), (2, NULL), (2, 'duplicate'), (4, 'four')");
        $semantics = new Semantics(Sqlite::Sqlite);
        $table = SemanticCases::table(Sqlite::Sqlite);
        $graph = new ModelGraph();
        $where = $filtered ? ' WHERE ' . $alias . '.foo > 1' : '';
        $ordering = $ordered ? ' ORDER BY original DESC' : '';
        $projection = $alias . '.' . $expression . ' AS original';
        $tail = ' FROM bar AS ' . $alias . $where . $ordering;
        $input = 'SELECT ' . $projection . $tail;
        $statement = $semantics->analyze($input, [$table]);
        self::assertInstanceOf(Select::class, $statement);
        $before = $graph->fingerprint($statement);
        $extra = new Field($statement->scope->column(new Name('label'), new QualifiedName(new Name($alias))), new Name('extra'));
        $updated = $statement->withFields($statement->fields()->addField($extra));
        $expected = $database->query('SELECT ' . $projection . ', ' . $alias . '.label AS extra' . $tail);
        $actual = $database->query($updated->toString());
        self::assertInstanceOf(PDOStatement::class, $expected);
        self::assertInstanceOf(PDOStatement::class, $actual);
        self::assertSame($expected->fetchAll(PDO::FETCH_NUM), $actual->fetchAll(PDO::FETCH_NUM), $input);
        self::assertSame($before, $graph->fingerprint($statement));
        self::assertSame($graph->fingerprint($updated), $graph->fingerprint($semantics->analyze($updated->toString(), [$table])));
    }

    /**
     * @return iterable<array{string, string, bool, bool}>
     */
    public static function providerProjectionCases(): iterable
    {
        foreach (['b', 'source'] as $alias) {
            foreach (['foo', 'label', 'foo + 1'] as $expression) {
                foreach ([false, true] as $filtered) {
                    foreach ([false, true] as $ordered) {
                        yield [$alias, $expression, $filtered, $ordered];
                    }
                }
            }
        }
    }
}

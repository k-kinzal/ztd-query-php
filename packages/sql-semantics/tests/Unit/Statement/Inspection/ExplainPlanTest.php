<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Inspection;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Inspection\ExplainPlan;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\DropTable;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Schema\Table;
use SqlSemantics\Statement\SemanticGraph;

#[CoversClass(ExplainPlan::class)]
#[Small]
final class ExplainPlanTest extends TestCase
{
    public function testToStringRequestsInspectionOfTheOriginalOperation(): void
    {
        $table = new Table(new QualifiedName(new Name('example')));
        $catalog = new Catalog(new SearchPath(new Name('main')), tables: $table);
        $operation = new DropTable(new TableReference($catalog, $table->name));
        $inspection = new ExplainPlan($operation);
        self::assertSame($operation, $inspection->operation);
        self::assertTrue((new SemanticGraph())->isSemanticOperation($inspection));
        self::assertSame('EXPLAIN QUERY PLAN DROP TABLE example', $inspection->toString());
        $database = new PDO('sqlite::memory:');
        $database->exec('CREATE TABLE example (id INTEGER)');
        $result = $database->query($inspection->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        $result->fetchAll();
        $remaining = $database->query("SELECT name FROM sqlite_schema WHERE name = 'example'");
        self::assertInstanceOf(PDOStatement::class, $remaining);
        self::assertSame('example', $remaining->fetchColumn());
    }
}

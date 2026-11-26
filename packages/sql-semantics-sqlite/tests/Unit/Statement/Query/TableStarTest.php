<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\MissingTable;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\TableStar;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Testing\Sqlite\InspectedStatement;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(TableStar::class)]
#[Medium]
final class TableStarTest extends TestCase
{
    public function testRenderWritesTheQualifierAndSelectsTheColumnsOfThatRelation(): void
    {
        $semantics = new Inspection();
        $tables = [$semantics->inspect('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)'), $semantics->inspect('CREATE TABLE u (c TEXT)')];
        $query = $semantics->inspect('select u.*, "t".* from t join u', $tables);

        self::assertInstanceOf(Select::class, $query->statement);
        self::assertInstanceOf(TableStar::class, $query->statement->columns[0]);
        self::assertSame('u', $query->statement->columns[0]->table->value);
        self::assertSame('SELECT u.*, t.* FROM t JOIN u', $query->toString());
        self::assertSame(['c', 'a', 'b'], array_map(static fn (object $field): ?string => $field->name?->value, [...$query->fields() ?? []]));
    }

    public function testRenderWritesANewlyBuiltQualifiedStarAndReportsAnUnknownQualifier(): void
    {
        $semantics = new Inspection();
        $operation = new InspectedStatement($semantics->context(), new Select([new TableStar(new Name('x'))], new TableInput(new QualifiedName(new Name('t')))));

        self::assertSame('SELECT x.* FROM t', $operation->toString());
        self::assertInstanceOf(MissingTable::class, $operation->facts->diagnostics[0]);
        self::assertSame('Relation x does not exist.', $operation->facts->diagnostics[0]->message());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Reference\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\CommonTable;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithQuery;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(CommonTable::class)]
#[Medium]
final class CommonTableTest extends TestCase
{
    public function testDefinitionIsTheCommonTableNodeOfTheStatement(): void
    {
        $query = (new Inspection())->inspect('WITH c AS (SELECT 1 AS a) SELECT a FROM c');
        $statement = $query->statement;

        self::assertInstanceOf(WithQuery::class, $statement);
        self::assertInstanceOf(Select::class, $statement->body);
        $input = $statement->body->input();
        self::assertNotNull($input);
        $resolution = $query->facts->relation($input)->table;
        self::assertInstanceOf(CommonTable::class, $resolution);
        self::assertSame($statement->with->tables[0], $resolution->definition);
        self::assertSame('a', $query->field('a')->name?->value);
    }

    public function testDefinitionShadowsADeclaredTableOfTheSameName(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE c (b INTEGER)');
        $query = $semantics->inspect('WITH c AS (SELECT 1 AS a) SELECT a FROM c', [$table]);
        $statement = $query->statement;

        self::assertInstanceOf(WithQuery::class, $statement);
        self::assertInstanceOf(Select::class, $statement->body);
        $input = $statement->body->input();
        self::assertNotNull($input);
        self::assertInstanceOf(CommonTable::class, $query->facts->relation($input)->table);
        self::assertNotInstanceOf(DeclaredTable::class, $query->facts->relation($input)->table);
    }
}

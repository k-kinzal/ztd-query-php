<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\Expression\SignedCast;
use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Schema\Query\CreateTableAs;
use SqlSemantics\Platform\MySql\Ir\Schema\Query\CreateTableAsLowering;
use SqlSemantics\Platform\MySql\Ir\TableSource;
use SqlSemantics\Platform\MySql\Model\Contract\SearchPath;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable as ParsedCreateTable;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(CreateTableAsLowering::class)]
#[Medium]
final class CreateTableAsLoweringTest extends TestCase
{
    public function testLowerUsesTheSameCheckedProjectionAsDirectConstruction(): void
    {
        $query = (new Inspection())->inspect('CREATE TEMPORARY TABLE app.report AS SELECT CAST(NULL AS SIGNED) AS missing');
        self::assertInstanceOf(ParsedCreateTable::class, $query->statement);

        $value = (new CreateTableAsLowering())->lower($query->binding(), $query->statement);

        self::assertSame(ExternalRelationKind::TemporaryTable, $value->target->kind);
        self::assertSame('app', $value->target->name->schema->value);
        self::assertSame('missing', $value->query->columns[0]->name->value);
        $term = $value->query->columns[0]->expression;
        self::assertInstanceOf(SignedCast::class, $term);
        self::assertInstanceOf(NullLiteral::class, $term->operand);
    }

    public function testLowerBindsQualifiedAndBareSpellingsToTheSameTemporaryInput(): void
    {
        $semantics = new Semantics();
        $declarations = $semantics->context([
            'CREATE TABLE app.accounts (id INT NOT NULL DEFAULT 9)',
            'CREATE TEMPORARY TABLE app.accounts (id INT DEFAULT 11)',
        ]);
        $value = $semantics->analyze(
            'CREATE TABLE app.copy AS SELECT id AS a, app.accounts.id AS b FROM app.accounts',
            $declarations
        );

        self::assertInstanceOf(CreateTableAs::class, $value);
        self::assertInstanceOf(TableSource::class, $value->query->source);
        self::assertSame(ExternalRelationKind::TemporaryTable, $value->query->source->kind);
        self::assertSame('id', $value->query->source->columns[0]->value);
        $expressions = array_column($value->query->columns, 'expression');
        self::assertContainsOnlyInstancesOf(ColumnReference::class, $expressions);
        self::assertSame([0, 0], array_column($expressions, 'occurrence'));
        self::assertSame([0, 0], array_column($expressions, 'slot'));
    }

    public function testAnalyzeAllRetainsTheDefaultAndExplicitDestinations(): void
    {
        $semantics = new Semantics(searchPath: new SearchPath('app'));

        [$local, $permanent] = $semantics->analyzeAll(
            'CREATE TEMPORARY TABLE report AS SELECT 1 AS n; CREATE TABLE archive.report AS SELECT 2 AS n;'
        );

        self::assertInstanceOf(CreateTableAs::class, $local);
        self::assertInstanceOf(CreateTableAs::class, $permanent);
        self::assertSame('app', $local->target->name->schema->value);
        self::assertSame('archive', $permanent->target->name->schema->value);
        self::assertSame(ExternalRelationKind::TemporaryTable, $local->target->kind);
        self::assertSame(ExternalRelationKind::BaseTable, $permanent->target->kind);
    }
}

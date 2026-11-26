<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Like;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Schema\Like\CreateTableLike;
use SqlSemantics\Platform\MySql\Ir\Schema\Like\CreateTableLikeLowering;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTableLike as ParsedCreateTableLike;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(CreateTableLikeLowering::class)]
#[Medium]
final class CreateTableLikeLoweringTest extends TestCase
{
    public function testAnalyzeAllPublishesTheVisibleSourceIdentity(): void
    {
        $analyzer = new Semantics();
        $context = $analyzer->context([
            'CREATE TABLE app.records (n INTEGER)',
            'CREATE TEMPORARY TABLE app.records (n SMALLINT)',
        ]);

        [$value] = $analyzer->analyzeAll('CREATE TABLE archive.copy LIKE app.records;', $context);

        self::assertInstanceOf(CreateTableLike::class, $value);
        self::assertSame('app', $value->source->name->schema->value);
        self::assertSame(ExternalRelationKind::TemporaryTable, $value->source->kind);
        self::assertSame(ExternalRelationKind::BaseTable, $value->destination->kind);
    }

    public function testLowerRetainsTheAbsoluteSourceAndDestination(): void
    {
        $inspection = new Inspection();
        $original = $inspection->inspect('CREATE TABLE app.records (n INTEGER)');
        $query = $inspection->inspect('CREATE TEMPORARY TABLE archive.copy LIKE app.records', [$original]);
        self::assertInstanceOf(ParsedCreateTableLike::class, $query->statement);

        $value = (new CreateTableLikeLowering())->lower($query->binding(), $query->statement);
        self::assertSame('app', $value->source->name->schema->value);
        self::assertSame('archive', $value->destination->name->schema->value);
        self::assertSame(ExternalRelationKind::BaseTable, $value->source->kind);
        self::assertSame(ExternalRelationKind::TemporaryTable, $value->destination->kind);
    }
}

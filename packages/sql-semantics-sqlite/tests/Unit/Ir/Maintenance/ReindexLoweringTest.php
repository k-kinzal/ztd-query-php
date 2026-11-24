<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Collation;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Reindex;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\ReindexLowering;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\ReindexRelation;
use SqlSemantics\Platform\Sqlite\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\Sqlite\Model\Contract\SearchPath;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(ReindexLowering::class)]
#[Small]
final class ReindexLoweringTest extends TestCase
{
    public function testLowerSelectsTheBuiltinBeforeALikeNamedTable(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE main.nocase (code TEXT)']);
        $value = $semantics->analyze('REINDEX nocase', $context);
        $table = $semantics->analyze('REINDEX main.nocase', $context);

        self::assertInstanceOf(Reindex::class, $value);
        self::assertSame(Collation::NoCase, $value->target);
        self::assertInstanceOf(Reindex::class, $table);
        self::assertInstanceOf(ReindexRelation::class, $table->target);
        self::assertSame('REINDEX "main"."nocase"', $table->toString());
    }

    public function testScopesKeepsTheExactAttachedSchemaCoordinate(): void
    {
        $context = (new Semantics(searchPath: new SearchPath('main', 'Aux', '')))->context([]);
        self::assertInstanceOf(AnalysisContext::class, $context);
        $lowering = new ReindexLowering();

        self::assertEquals([new Name('Aux')], $lowering->scopes($context, new Name('aux')));
        self::assertEquals([new Name('')], $lowering->scopes($context, new Name('')));
    }

    public function testRelationUsesTheFirstResolvedTableNamespace(): void
    {
        $context = (new Semantics())->context([
            'CREATE TABLE main.records (id INTEGER)',
            'CREATE TABLE temp.records (id INTEGER)',
        ]);
        self::assertInstanceOf(AnalysisContext::class, $context);
        $target = (new ReindexLowering())->relation($context, new QualifiedName(new Name('records')), $context->searchPath);

        self::assertNotNull($target);
        self::assertSame('temp', $target->schema->value);
    }

    public function testIndexKeepsTheOwnerOfAnImplicitIndex(): void
    {
        $context = (new Semantics())->context(['CREATE TABLE records (id INT PRIMARY KEY)']);
        self::assertInstanceOf(AnalysisContext::class, $context);
        $target = (new ReindexLowering())->index($context, new Name('sqlite_autoindex_records_1'), [new Name('main')]);

        self::assertSame('main', $target->schema->value);
        self::assertSame('records', $target->table->value);
    }
}

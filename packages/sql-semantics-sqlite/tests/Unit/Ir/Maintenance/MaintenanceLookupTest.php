<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\MaintenanceLookup;
use SqlSemantics\Platform\Sqlite\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\Sqlite\Model\Contract\SearchPath;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(MaintenanceLookup::class)]
#[Small]
final class MaintenanceLookupTest extends TestCase
{
    public function testScopesKeepsTheExactAttachedSchemaCoordinate(): void
    {
        $context = (new Semantics(searchPath: new SearchPath('main', 'Aux', '')))->context([]);
        self::assertInstanceOf(AnalysisContext::class, $context);
        $lowering = new MaintenanceLookup();

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
        $target = (new MaintenanceLookup())->relation($context, new QualifiedName(new Name('records')), $context->searchPath);

        self::assertNotNull($target);
        self::assertSame('temp', $target->schema->value);
    }

    public function testIndexKeepsTheOwnerOfAnImplicitIndex(): void
    {
        $context = (new Semantics())->context(['CREATE TABLE records (id INT PRIMARY KEY)']);
        self::assertInstanceOf(AnalysisContext::class, $context);
        $target = (new MaintenanceLookup())->index($context, new QualifiedName(new Name('sqlite_autoindex_records_1')), [new Name('main')]);

        self::assertNotNull($target);
        self::assertSame('main', $target->schema->value);
        self::assertSame('records', $target->table->value);
    }

    public function testSchemaUsesAsciiNameComparison(): void
    {
        $lookup = new MaintenanceLookup();

        self::assertSame('Extension', $lookup->schema(new Name('extension'), [new Name('Extension')])?->value);
        self::assertNull($lookup->schema(new Name('unknown'), [new Name('Extension')]));
    }

    public function testPathKeepsTheNativeSchemaOrder(): void
    {
        $context = (new Semantics(searchPath: new SearchPath('main', 'extra')))->context([]);
        self::assertInstanceOf(AnalysisContext::class, $context);

        self::assertSame(['temp', 'main', 'extra'], array_column((new MaintenanceLookup())->path($context), 'value'));
    }
}

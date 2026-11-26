<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Namespace\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\CreateSchema;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\View\SchemaViewInputs;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(SchemaViewInputs::class)]
final class SchemaViewInputsTest extends TestCase
{
    public function testCheckUsesTheEarlierViewHeadingAndDependentNativeType(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE VIEW public.provider (n) AS SELECT 402']);
        $result = $semantics->analyze(
            'CREATE SCHEMA archive CREATE VIEW provider (n,missing) AS SELECT 33,NULL'
            . ' CREATE VIEW leaf AS SELECT missing,n FROM provider',
            $context
        );

        self::assertInstanceOf(CreateSchema::class, $result);
        $query = $result->views[1]->query;
        self::assertInstanceOf(Select::class, $query);
        self::assertInstanceOf(TableSource::class, $query->source);
        self::assertSame('archive', $query->source->schema->value);
        self::assertSame(ExternalRelationKind::View, $query->source->kind);
        self::assertSame([ColumnKind::Text, ColumnKind::Int4], $query->source->columnKinds);
    }

    public function testCheckRejectsADirectViewBoundBeforeItsProvider(): void
    {
        $semantics = new Semantics();
        $source = $semantics->analyze(
            'CREATE SCHEMA archive CREATE VIEW source (amount) AS VALUES (2),(2)'
            . ' CREATE VIEW leaf AS SELECT amount FROM source',
            $semantics->context([])
        );
        self::assertInstanceOf(CreateSchema::class, $source);
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('A schema view must resolve its local view input to a preceding ordinary view.');

        new CreateSchema($source->name, views: array_reverse($source->views));
    }
}

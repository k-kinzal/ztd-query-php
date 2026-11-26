<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Namespace\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextCollation;
use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\View\SchemaViewFields;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\CreateView;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(SchemaViewFields::class)]
final class SchemaViewFieldsTest extends TestCase
{
    public function testOfResolvesKnownPhysicalCollationsAndPreservesAnUnknownNullAsText(): void
    {
        $source = (new Semantics())->analyze(
            'CREATE VIEW feed AS SELECT NULL AS hidden, \'known\' COLLATE pg_catalog."C" AS caption, 2 AS n'
        );
        self::assertInstanceOf(CreateView::class, $source);

        self::assertSame([
            'hidden' => [ColumnKind::Text, null],
            'caption' => [ColumnKind::Text, TextCollation::C],
            'n' => [ColumnKind::Int4, null],
        ], SchemaViewFields::of($source, []));
    }
}

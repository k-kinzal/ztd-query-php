<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Catalog\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Lowering\Catalog\Index\AnonymousIndexFields;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(AnonymousIndexFields::class)]
#[Small]
final class AnonymousIndexFieldsTest extends TestCase
{
    public function testResolveKeepsRepeatedKeyAndIncludedOccurrences(): void
    {
        $context = (new Semantics())->context([
            'CREATE TABLE public.items (value integer, payload integer)',
            'CREATE INDEX ON public.items (value, value) INCLUDE (value, payload)',
        ]);

        self::assertInstanceOf(AnalysisContext::class, $context);
        self::assertCount(1, $context->indexNamespace->indexes);
        self::assertSame('items_value_value1_value2_payload_idx', $context->indexNamespace->indexes[0]->index->value);
    }
}

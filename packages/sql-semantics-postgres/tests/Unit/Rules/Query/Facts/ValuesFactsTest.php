<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Facts;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Query\Facts\ValuesFacts::class)]
#[Small]
final class ValuesFactsTest extends TestCase
{
    public function testDeriveResolvesEachColumn(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze("VALUES (1, NULL), (2.5, 'a')");
        self::assertSame(['numeric', 'text'], array_map(static fn (\SqlSemantics\Statement\Shape\Field $field): ?string => ($field->type instanceof \SqlSemantics\Statement\Type\Known ? $field->type->descriptor->name() : null), $query->fields()->items ?? []));
    }
}

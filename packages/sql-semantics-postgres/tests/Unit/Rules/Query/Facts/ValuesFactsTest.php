<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Facts;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Query\Facts\ValuesFacts::class)]
#[Medium]
final class ValuesFactsTest extends TestCase
{
    public function testDeriveResolvesEachColumn(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $query = $semantics->inspect("VALUES (1, NULL), (2.5, 'a')");
        self::assertSame(['numeric', 'text'], array_map(static fn (\SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\Field $field): ?string => ($field->type instanceof \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known ? $field->type->descriptor->name() : null), $query->fields()->items ?? []));
    }
}

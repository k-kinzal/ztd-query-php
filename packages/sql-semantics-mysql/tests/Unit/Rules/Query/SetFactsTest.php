<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\SetFacts;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(SetFacts::class)]
#[Medium]
final class SetFactsTest extends TestCase
{
    public function testDeriveSeesARecursiveTableWithTheColumnsOfItsAnchor(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("WITH RECURSIVE c AS (SELECT 1 AS n UNION ALL SELECT CONCAT(n, 'x') FROM c) SELECT n FROM c", []);
        $type = $operation->field('n')->type;

        self::assertSame([], $operation->facts->diagnostics);
        self::assertInstanceOf(Known::class, $type);
        self::assertSame('BIGINT', $type->descriptor->name());
        self::assertSame(Nullability::Nullable, $operation->field('n')->nullability);
    }
}

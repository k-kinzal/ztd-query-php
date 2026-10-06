<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SortItem;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;

#[CoversClass(Clause::class)]
#[Small]
final class ClauseTest extends TestCase
{
    public function testDeriveClauseIsOfferedByEveryPartThatMayHoldExpressions(): void
    {
        self::assertContains(Clause::class, class_implements(TypeName::class));
        self::assertContains(Clause::class, class_implements(SortItem::class));
        self::assertContains(Clause::class, class_implements(Definition::class));
    }
}

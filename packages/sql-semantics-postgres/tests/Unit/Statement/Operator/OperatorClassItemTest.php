<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Operator\OperatorClassItem;
use SqlSemantics\Platform\PostgreSql\Statement\Operator\StorageMember;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(OperatorClassItem::class)]
#[Small]
final class OperatorClassItemTest extends TestCase
{
    public function testItemsAreClauses(): void
    {
        self::assertContains(Clause::class, class_implements(new StorageMember(new TypeName(new NamedDesignation(new DottedName([new Name('int4')]))))));
    }
}

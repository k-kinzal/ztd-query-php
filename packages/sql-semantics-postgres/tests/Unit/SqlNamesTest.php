<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\PostgreSql\Semantics;
use SqlSemantics\Platform\PostgreSql\SqlNames;

#[CoversClass(SqlNames::class)]
#[Medium]
final class SqlNamesTest extends TestCase
{
    public function testTableQuotesTheNameInTheSelectedDatabase(): void
    {
        $name = new QualifiedName(new Name('order items'), new Name('shop'));

        self::assertSame('shop."order items"', SqlNames::table($name, (new Semantics())->profile()));
    }
}

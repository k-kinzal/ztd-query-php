<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\AttributeChange::class)]
#[Medium]
final class AttributeChangeTest extends TestCase
{
    public function testDropAttributeIsAChange(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TYPE pair DROP ATTRIBUTE a')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\AlterComposite::class, $statement);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\DropAttribute::class, $statement->changes[0]::class);
    }
}

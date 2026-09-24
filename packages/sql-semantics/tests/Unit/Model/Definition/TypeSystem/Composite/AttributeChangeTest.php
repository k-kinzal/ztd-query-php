<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\TypeSystem\Composite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\TypeSystem\Composite;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Type\AlterCompositeTypeStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Composite\AttributeChange::class)]
#[Medium]
final class AttributeChangeTest extends TestCase
{
    public function testEveryCommandIsAnAttributeChange(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TYPE t ADD ATTRIBUTE a text, DROP ATTRIBUTE b, ALTER ATTRIBUTE c TYPE integer');
        self::assertInstanceOf(AlterCompositeTypeStatement::class, $statement);
        self::assertContainsOnlyInstancesOf(Composite\AttributeChange::class, $statement->changes);
        self::assertInstanceOf(Composite\RetypeAttribute::class, $statement->changes[2]);
    }
}

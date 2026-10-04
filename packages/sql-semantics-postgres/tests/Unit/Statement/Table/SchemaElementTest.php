<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\SchemaElement::class)]
#[Medium]
final class SchemaElementTest extends TestCase
{
    public function testCreatedSchemaIsNullForAnUnqualifiedName(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE INDEX ON t (a)', []);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Index\CreateIndex::class, $n1);
        self::assertSame(null, $n1->createdSchema());
    }

    public function testDeriveElementLocatesTheTableInTheSchema(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE INDEX ON t (a)');
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\SchemaElement::class, $statement->statement);
        $derivation = new \SqlSemantics\Construction\Derivation($statement->context);
        $statement->statement->deriveElement($derivation, new \SqlSemantics\Statement\Identifier\Name('s'));
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Index\CreateIndex::class, $n1);
        $n2 = $derivation->facts()->relation($n1)->table;
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Table\UndeclaredTable::class, $n2);
        self::assertSame('s', $n2->missing->name->schema?->value);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Sequence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\CreateSequence::class)]
#[Medium]
final class CreateSequenceTest extends TestCase
{
    public function testCreatedSchemaIsTheWrittenSchema(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $statement = $semantics->inspect('CREATE SEQUENCE x.s', []);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\CreateSequence::class, $n1);
        self::assertSame('x', $n1->createdSchema()?->value);
    }

    public function testDeriveStatementDeclaresTheSequence(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $statement = $semantics->inspect('CREATE SEQUENCE s', []);
        self::assertSame([
          0 => 'last_value bigint NotNull',
          1 => 'log_cnt bigint NotNull',
          2 => 'is_called boolean NotNull',
        ], array_map(static fn ($column): string => $column->name->value . ' ' . $column->type->name() . ' ' . $column->nullability->name, $statement->declarations()[0]->columns));
    }

    public function testDeriveElementDeclaresTheSequenceInTheSchema(): void
    {
        $statement = (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('CREATE SEQUENCE q');
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\SchemaElement::class, $statement->statement);
        $derivation = new \SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation($statement->context);
        $statement->statement->deriveElement($derivation, new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('s'));
        self::assertSame('s', $derivation->facts()->declarations[0]->name->schema?->value);
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $statement = $semantics->inspect('CREATE UNLOGGED SEQUENCE IF NOT EXISTS s AS integer START WITH 1 CACHE 10 NO MINVALUE OWNED BY NONE', []);
        self::assertSame('CREATE UNLOGGED SEQUENCE IF NOT EXISTS s AS INTEGER START 1 CACHE 10 NO MINVALUE OWNED BY "none"', $statement->toString());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Construction\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Construction as C;
use SqlSemantics\Statement\Expression as E;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query\Select;

#[CoversClass(C\Query\SelectDefinition::class)]
#[Small]
final class SelectDefinitionTest extends TestCase
{
    public function testNewUsesResolveToTheOriginalDeclarationAndFreshOccurrences(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE items (id INTEGER)');
        $context = $semantics->context([$table]);
        $input = new C\Query\SelectDefinition(new C\Query\ProjectionDefinition(new C\Query\FieldDefinition(new C\Expression\ColumnUse(new Name('id')))), new C\Query\Inputs(new C\Query\NamedInput(new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('items')))));
        $first = new Select($context, $input);
        $second = new Select($context, $input);
        $left = $first->field('id')->expression;
        $right = $second->field('id')->expression;
        self::assertInstanceOf(E\ColumnReference::class, $left);
        self::assertInstanceOf(E\ColumnReference::class, $right);
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\ResolvedColumn::class, $left->resolution);
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\ResolvedColumn::class, $right->resolution);
        self::assertSame($left->resolution->column, $right->resolution->column);
        self::assertNotSame($first->singleNamedInput(), $second->singleNamedInput());
        self::assertSame($first->singleNamedInput(), $left->resolution->relation);
        self::assertSame($second->singleNamedInput(), $right->resolution->relation);
    }
}

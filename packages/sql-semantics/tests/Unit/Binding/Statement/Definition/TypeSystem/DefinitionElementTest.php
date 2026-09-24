<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\TypeSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Definition\TypeSystem\DefinitionElement;
use SqlSemantics\Binding\TableResolver;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DefinitionElement::class)]
#[Medium]
final class DefinitionElementTest extends TestCase
{
    public function testListReadsNamesAndArgumentsInOrder(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build();
        $tree = (new DialectParser(Dialect::PostgreSql))->parse('ALTER OPERATOR = (integer, integer) SET (Restrict = NONE, "Join", hashes = true)');
        $context = new QueryContext(new TableResolver($schema, new Identifiers(Dialect::PostgreSql), 'public'));
        $elements = DefinitionElement::list(Tree::outer($tree, ['operator_def_list'])[0], $context);
        self::assertSame(['restrict', 'Join', 'hashes'], array_map(static fn (DefinitionElement $element): string => $element->name, $elements));
        self::assertNull($elements[0]->argument);
        self::assertNull($elements[1]->argument);
        self::assertSame('true', $elements[2]->argument === null ? null : Tree::text($elements[2]->argument));
    }

    public function testNamedKeysElementsAndRejectsRepetitionUnlessTheLastCounts(): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), 'public'));
        $elements = DefinitionElement::list((new DialectParser(Dialect::PostgreSql))->parse('CREATE OPERATOR === (a = 1, b = 2, a = 3)'), $context);
        self::assertSame('3', Tree::text(DefinitionElement::named($elements, ['a', 'b'], true)['a']->argument ?? $elements[0]->source));
        $this->expectException(InvalidSql::class);
        DefinitionElement::named($elements, ['a', 'b'], false);
    }
}

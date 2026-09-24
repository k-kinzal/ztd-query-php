<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\TypeSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Definition\TypeSystem\DefinitionArguments;
use SqlSemantics\Binding\Statement\Definition\TypeSystem\DefinitionElement;
use SqlSemantics\Binding\TableResolver;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\TypeSystem\Definition\OperatorAttribute;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(DefinitionArguments::class)]
#[Medium]
final class DefinitionArgumentsTest extends TestCase
{
    public function testOptionSetsABooleanWithoutArgumentAndLeavesOthersNone(): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), 'public'));
        $elements = DefinitionElement::list((new DialectParser(Dialect::PostgreSql))->parse('ALTER OPERATOR = (integer, integer) SET (hashes, restrict)'), $context);
        self::assertTrue(DefinitionArguments::option($elements[0], OperatorAttribute::Hashes, $context)->value);
        self::assertNull(DefinitionArguments::option($elements[1], OperatorAttribute::Restrict, $context)->value);
    }

    public function testPresentRejectsAMissingArgument(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::DefinitionArgument->message());
        (new \SqlSemantics\Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TYPE t (input = i, output = o, delimiter)');
    }

    /**
     * @param list<string> $expected
     */
    #[TestWith(['s.f', ['s', 'f']])]
    #[TestWith(["'F'", ['F']])]
    #[TestWith(['ALL', ['all']])]
    #[TestWith(['NONE', ['none']])]
    #[TestWith(['OPERATOR(s.+)', ['s', '+']])]
    public function testNameReadsEveryNameSpelling(string $argument, array $expected): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), 'public'));
        $node = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('CREATE OPERATOR === (x = ' . $argument . ')'), ['def_arg'])[0];
        self::assertSame($expected, DefinitionArguments::name($node, $context)->parts);
    }

    #[TestWith(['1'])]
    #[TestWith(['integer[]'])]
    public function testNameRejectsNumbersAndTypeDeclarations(string $argument): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), 'public'));
        $node = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('CREATE OPERATOR === (x = ' . $argument . ')'), ['def_arg'])[0];
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::DefinitionArgument->message());
        DefinitionArguments::name($node, $context);
    }

    public function testObjectNameRejectsAnOverQualifiedName(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::CatalogObjectName->message());
        (new \SqlSemantics\Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH CONFIGURATION c (parser = a.b.c)');
    }

    public function testOperatorReadsASchemaQualifiedSymbol(): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), 'public'));
        $node = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('CREATE OPERATOR === (x = OPERATOR(pg_catalog.<>))'), ['def_arg'])[0];
        self::assertSame(['pg_catalog', '<>'], DefinitionArguments::operator($node, $context)->parts);
    }

    public function testTypeReadsDeclarationsAndNames(): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), 'public'));
        $tree = (new DialectParser(Dialect::PostgreSql))->parse("CREATE OPERATOR === (x = numeric(10, 2), y = 'int4')");
        $nodes = Tree::outer($tree, ['def_arg']);
        $declared = DefinitionArguments::type($nodes[0], $context);
        self::assertInstanceOf(TypeDescriptor::class, $declared);
        self::assertSame('numeric', $declared->identity->name());
        $named = DefinitionArguments::type($nodes[1], $context);
        self::assertInstanceOf(TypeDescriptor::class, $named);
        self::assertSame('int4', $named->name);
    }

    #[TestWith(['on', true])]
    #[TestWith(['OFF', false])]
    #[TestWith(["'True'", true])]
    #[TestWith(['0', false])]
    public function testBooleanReadsTheServerSpellings(string $argument, bool $expected): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), 'public'));
        $node = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('CREATE OPERATOR === (x = ' . $argument . ')'), ['def_arg'])[0];
        self::assertSame($expected, DefinitionArguments::boolean($node, $context));
    }

    public function testIntegerRejectsAFraction(): void
    {
        $node = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('CREATE OPERATOR === (x = 1.5)'), ['def_arg'])[0];
        $this->expectException(InvalidSql::class);
        DefinitionArguments::integer($node);
    }

    #[TestWith(["'a''b'", "a'b"])]
    #[TestWith(['007', '7'])]
    #[TestWith(['-1.50', '-1.50'])]
    #[TestWith(['s.x', 's.x'])]
    public function testTextReadsWhatTheServerReads(string $argument, string $expected): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), 'public'));
        $node = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('CREATE OPERATOR === (x = ' . $argument . ')'), ['def_arg'])[0];
        self::assertSame($expected, DefinitionArguments::text($node, $context));
    }

    /**
     * @param list<string>|null $expected
     */
    #[TestWith(['char', ['pg_catalog', 'bpchar']])]
    #[TestWith(['double precision', ['pg_catalog', 'float8']])]
    #[TestWith(['int[]', null])]
    #[TestWith(['s.t', null])]
    public function testSystemSpellsKeywordTypesByCatalogName(string $argument, ?array $expected): void
    {
        $node = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('CREATE OPERATOR === (x = ' . $argument . ')'), ['def_arg'])[0];
        self::assertSame($expected, DefinitionArguments::system($node));
    }

    public function testWordsIsNullForAModifiedType(): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), 'public'));
        $node = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('CREATE OPERATOR === (x = varchar(3))'), ['def_arg'])[0];
        self::assertNull(DefinitionArguments::words($node, $context));
    }
}

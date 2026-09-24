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
use SqlSemantics\Model\Definition\TypeSystem\Definition\AggregateAttribute;
use SqlSemantics\Model\Definition\TypeSystem\Definition\BaseTypeAttribute;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionAttribute;
use SqlSemantics\Model\Definition\TypeSystem\Definition\OperatorAttribute;
use SqlSemantics\Model\Relation\QualifiedName;
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

    public function testOptionConvertsEachArgumentKind(): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), 'public'));
        $elements = DefinitionElement::list((new DialectParser(Dialect::PostgreSql))->parse("CREATE TYPE t (a = s.f, b = OPERATOR(s.+), c = integer, d = true, e = 8, f = 'Variable', g = -1, h = 16, i = 'U', j = PLAIN)"), $context);
        $name = DefinitionArguments::option($elements[0], BaseTypeAttribute::Input, $context)->value;
        self::assertInstanceOf(QualifiedName::class, $name);
        self::assertSame(['s', 'f'], $name->parts);
        $operator = DefinitionArguments::option($elements[1], OperatorAttribute::Commutator, $context)->value;
        self::assertInstanceOf(QualifiedName::class, $operator);
        self::assertSame(['s', '+'], $operator->parts);
        $type = DefinitionArguments::option($elements[2], OperatorAttribute::LeftArg, $context)->value;
        self::assertInstanceOf(TypeDescriptor::class, $type);
        self::assertSame('integer', $type->name);
        self::assertTrue(DefinitionArguments::option($elements[3], BaseTypeAttribute::Preferred, $context)->value);
        self::assertSame(8, DefinitionArguments::option($elements[4], AggregateAttribute::Sspace, $context)->value);
        self::assertSame(-1, DefinitionArguments::option($elements[5], BaseTypeAttribute::InternalLength, $context)->value);
        self::assertSame(-1, DefinitionArguments::option($elements[6], BaseTypeAttribute::InternalLength, $context)->value);
        self::assertSame(16, DefinitionArguments::option($elements[7], BaseTypeAttribute::InternalLength, $context)->value);
        self::assertSame('U', DefinitionArguments::option($elements[8], BaseTypeAttribute::Category, $context)->value);
        self::assertSame('plain', DefinitionArguments::option($elements[9], BaseTypeAttribute::Storage, $context)->value);
    }

    #[TestWith(['abc', OperatorAttribute::Commutator])]
    #[TestWith(['40000', BaseTypeAttribute::InternalLength])]
    #[TestWith(['bogus', BaseTypeAttribute::Storage])]
    public function testOptionRejectsAValueOfAnotherForm(string $argument, DefinitionAttribute $attribute): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), 'public'));
        $elements = DefinitionElement::list((new DialectParser(Dialect::PostgreSql))->parse('CREATE TYPE t (a = ' . $argument . ')'), $context);
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::DefinitionArgument->message());
        DefinitionArguments::option($elements[0], $attribute, $context);
    }

    public function testPresentReturnsASpelledArgument(): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), 'public'));
        $elements = DefinitionElement::list((new DialectParser(Dialect::PostgreSql))->parse("CREATE TYPE t (category = 'U')"), $context);
        self::assertSame('U', DefinitionArguments::present($elements[0], BaseTypeAttribute::Category, $context)->value);
    }

    public function testObjectNameReadsASchemaQualifiedName(): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), 'public'));
        $elements = DefinitionElement::list((new DialectParser(Dialect::PostgreSql))->parse('CREATE TYPE t (a = s.f)'), $context);
        self::assertSame(['s', 'f'], DefinitionArguments::objectName($elements[0], $context)->parts);
    }

    public function testObjectNameRejectsAMissingArgument(): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), 'public'));
        $elements = DefinitionElement::list((new DialectParser(Dialect::PostgreSql))->parse('ALTER OPERATOR = (integer, integer) SET (restrict)'), $context);
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::DefinitionArgument->message());
        DefinitionArguments::objectName($elements[0], $context);
    }

    public function testOperatorRejectsAnOverQualifiedSymbol(): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), 'public'));
        $node = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('CREATE OPERATOR === (x = OPERATOR(a.b.+))'), ['def_arg'])[0];
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::CatalogObjectName->message());
        DefinitionArguments::operator($node, $context);
    }

    public function testOperatorRejectsANumber(): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), 'public'));
        $node = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('CREATE OPERATOR === (x = 1)'), ['def_arg'])[0];
        $this->expectException(InvalidSql::class);
        DefinitionArguments::operator($node, $context);
    }

    #[TestWith(['+'])]
    #[TestWith(['1'])]
    public function testTypeRejectsOperatorsAndNumbers(string $argument): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), 'public'));
        $node = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('CREATE OPERATOR === (x = ' . $argument . ')'), ['def_arg'])[0];
        $this->expectException(InvalidSql::class);
        DefinitionArguments::type($node, $context);
    }

    #[TestWith(['false', false])]
    #[TestWith(['1', true])]
    public function testBooleanReadsFalseAndOne(string $argument, bool $expected): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), 'public'));
        $node = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('CREATE OPERATOR === (x = ' . $argument . ')'), ['def_arg'])[0];
        self::assertSame($expected, DefinitionArguments::boolean($node, $context));
    }

    public function testBooleanRejectsOtherWords(): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), 'public'));
        $node = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('CREATE OPERATOR === (x = maybe)'), ['def_arg'])[0];
        $this->expectException(InvalidSql::class);
        DefinitionArguments::boolean($node, $context);
    }

    #[TestWith(['- 5', -5])]
    #[TestWith(['1_000', 1000])]
    #[TestWith(['2147483647', 2147483647])]
    public function testIntegerReadsSignedConstants(string $argument, int $expected): void
    {
        $node = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('CREATE OPERATOR === (x = ' . $argument . ')'), ['def_arg'])[0];
        self::assertSame($expected, DefinitionArguments::integer($node));
    }

    public function testIntegerRejectsAnOverflow(): void
    {
        $node = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('CREATE OPERATOR === (x = 2147483648)'), ['def_arg'])[0];
        $this->expectException(InvalidSql::class);
        DefinitionArguments::integer($node);
    }

    #[TestWith(['02147483647', '2147483647'])]
    #[TestWith(['+', '+'])]
    #[TestWith(['OPERATOR(s.+)', 's.+'])]
    #[TestWith(['integer', 'pg_catalog.int4'])]
    public function testTextReadsNumbersOperatorsAndKeywordTypes(string $argument, string $expected): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), 'public'));
        $node = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('CREATE OPERATOR === (x = ' . $argument . ')'), ['def_arg'])[0];
        self::assertSame($expected, DefinitionArguments::text($node, $context));
    }

    public function testTextRejectsAnArrayType(): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), 'public'));
        $node = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('CREATE OPERATOR === (x = integer[])'), ['def_arg'])[0];
        $this->expectException(InvalidSql::class);
        DefinitionArguments::text($node, $context);
    }

    /**
     * @param list<string>|null $expected
     */
    #[TestWith(['SETOF integer', null])]
    #[TestWith(['integer', ['pg_catalog', 'int4']])]
    #[TestWith(['text', null])]
    #[TestWith(['int4[]', null])]
    public function testSystemSkipsSetsArraysAndGenericTypes(string $argument, ?array $expected): void
    {
        $node = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('CREATE OPERATOR === (x = ' . $argument . ')'), ['def_arg'])[0];
        self::assertSame($expected, DefinitionArguments::system($node));
    }

    /**
     * @param list<string>|null $expected
     */
    #[TestWith(['s.t', ['s', 't']])]
    #[TestWith(['s.t[]', null])]
    #[TestWith(['mytype(3)', null])]
    #[TestWith(['integer', null])]
    public function testWordsReadsOnlyPlainNames(string $argument, ?array $expected): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), 'public'));
        $node = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('CREATE OPERATOR === (x = ' . $argument . ')'), ['def_arg'])[0];
        self::assertSame($expected, DefinitionArguments::words($node, $context));
    }
}

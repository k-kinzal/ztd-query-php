<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\TypeSystem\Definition\BaseTypeAttribute;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionOption;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Type\CreateBaseTypeStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(CreateBaseTypeStatement::class)]
#[Medium]
final class CreateBaseTypeStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("CREATE TYPE s.t (input = t_in, output = t_out, internallength = 16, alignment = double precision, category = 'U', passedbyvalue = off, element = float4, subscript = raw_array_subscript_handler)");
        self::assertInstanceOf(CreateBaseTypeStatement::class, $statement);
        self::assertSame(BaseTypeAttribute::Alignment, $statement->options[3]->attribute);
        self::assertSame('double', $statement->options[3]->value);
        self::assertSame(16, $statement->options[2]->value);
        self::assertSame(StatementKind::Create, $statement->kind);
        self::assertSame("CREATE TYPE \"s\".\"t\"(INPUT = \"t_in\", OUTPUT = \"t_out\", INTERNALLENGTH = 16, ALIGNMENT = 'double', CATEGORY = 'U', PASSEDBYVALUE = FALSE, ELEMENT = real, SUBSCRIPT = \"raw_array_subscript_handler\")", $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testRejectsANonPrintableCategory(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TYPE t (input = i, output = o)');
        self::assertInstanceOf(CreateBaseTypeStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOptions([...$statement->options, new DefinitionOption(BaseTypeAttribute::Category, "\u{e9}")]);
    }

    public function testRejectsAnElementWithoutSubscripting(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TYPE t (input = i, output = o)');
        self::assertInstanceOf(CreateBaseTypeStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOptions([...$statement->options, new DefinitionOption(BaseTypeAttribute::Element, TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'))]);
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TYPE t (input = i, output = o)');
        self::assertInstanceOf(CreateBaseTypeStatement::class, $statement);
        self::assertSame('CREATE TYPE "t"(INPUT = "i", OUTPUT = "o")', $statement->withOrigin($statement->origin)->toString());
    }

    public function testWithNameReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TYPE t (input = i, output = o)');
        self::assertInstanceOf(CreateBaseTypeStatement::class, $statement);
        self::assertSame(['s', 'u'], $statement->withName(new QualifiedName(['s', 'u']))->name->parts);
        self::assertSame(['t'], $statement->name->parts);
    }

    public function testWithOptionsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TYPE t (input = i, output = o)');
        self::assertInstanceOf(CreateBaseTypeStatement::class, $statement);
        self::assertSame("CREATE TYPE \"t\"(INPUT = \"i\", OUTPUT = \"o\", STORAGE = 'plain')", $statement->withOptions([...$statement->options, new DefinitionOption(BaseTypeAttribute::Storage, 'plain')])->toString());
    }

    #[\PHPUnit\Framework\Attributes\TestWith([' '])]
    #[\PHPUnit\Framework\Attributes\TestWith(['~'])]
    public function testAcceptsTheEdgesOfPrintableAscii(string $category): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TYPE t (input = i, output = o)');
        self::assertInstanceOf(CreateBaseTypeStatement::class, $statement);
        self::assertCount(3, $statement->withOptions([...$statement->options, new DefinitionOption(BaseTypeAttribute::Category, $category)])->options);
    }

    #[\PHPUnit\Framework\Attributes\TestWith([''])]
    #[\PHPUnit\Framework\Attributes\TestWith(["\x1f"])]
    #[\PHPUnit\Framework\Attributes\TestWith(["\x7f"])]
    public function testRejectsACategoryOutsidePrintableAscii(string $category): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TYPE t (input = i, output = o)');
        self::assertInstanceOf(CreateBaseTypeStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('A type category is one printable ASCII character.');
        $statement->withOptions([...$statement->options, new DefinitionOption(BaseTypeAttribute::Category, $category)]);
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TYPE t (input = i, output = o)');
        self::assertInstanceOf(CreateBaseTypeStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new CreateBaseTypeStatement(new \SqlSemantics\Model\Statement\Origin('s0', $statement->source, Dialect::MySql), $statement->name, $statement->options);
    }

    public function testRejectsAnOverQualifiedName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TYPE t (input = i, output = o)');
        self::assertInstanceOf(CreateBaseTypeStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withName(new QualifiedName(['a', 'b', 'c', 'd']));
    }

    /**
     * @param non-empty-list<DefinitionOption> $options
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidOptions')]
    public function testRejectsAnIncompleteOrForeignOptionList(array $options): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TYPE t (input = i, output = o)');
        self::assertInstanceOf(CreateBaseTypeStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOptions($options);
    }

    /**
     * @return iterable<string, array{non-empty-list<DefinitionOption>}>
     */
    public static function providerInvalidOptions(): iterable
    {
        $input = new DefinitionOption(BaseTypeAttribute::Input, new QualifiedName(['i']));
        $output = new DefinitionOption(BaseTypeAttribute::Output, new QualifiedName(['o']));
        yield 'foreign attribute' => [[$input, $output, new DefinitionOption(\SqlSemantics\Model\Definition\TypeSystem\Definition\AggregateAttribute::Sspace, 4)]];
        yield 'absent value' => [[$input, $output, new DefinitionOption(BaseTypeAttribute::Receive, null)]];
        yield 'missing input' => [[$output]];
        yield 'missing output' => [[$input]];
    }
}

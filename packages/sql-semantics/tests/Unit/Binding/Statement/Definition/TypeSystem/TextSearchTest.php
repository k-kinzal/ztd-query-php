<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\TypeSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Definition\TypeSystem\DefinitionElement;
use SqlSemantics\Binding\Statement\Definition\TypeSystem\TextSearch;
use SqlSemantics\Binding\TableResolver;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\TypeSystem\TextSearch\MappingChange;
use SqlSemantics\Model\Statement\Definition\PostgreSql\TextSearch as Statement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TextSearch::class)]
#[Medium]
final class TextSearchTest extends TestCase
{
    /**
     * @param class-string<object> $class
     */
    #[TestWith(['CREATE TEXT SEARCH PARSER p (start = s, gettoken = g, end = e, lextypes = l)', Statement\CreateTextSearchParserStatement::class])]
    #[TestWith(['CREATE TEXT SEARCH TEMPLATE t (lexize = l)', Statement\CreateTextSearchTemplateStatement::class])]
    #[TestWith(['CREATE TEXT SEARCH DICTIONARY d (template = simple)', Statement\CreateTextSearchDictionaryStatement::class])]
    #[TestWith(['CREATE TEXT SEARCH CONFIGURATION c (parser = default)', Statement\CreateTextSearchConfigurationStatement::class])]
    #[TestWith(['CREATE TEXT SEARCH CONFIGURATION c (copy = english)', Statement\CopyTextSearchConfigurationStatement::class])]
    public function testCreateRoutesByObjectClass(string $sql, string $class): void
    {
        self::assertInstanceOf($class, (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql));
    }

    #[TestWith(['CREATE TEXT SEARCH PARSER p (start = s, gettoken = g, end = e)', 'definition-requirement'])]
    #[TestWith(['CREATE TEXT SEARCH PARSER p (start = s, gettoken = g, end = e, lextypes = l, "START" = s)', 'definition-attribute'])]
    #[TestWith(['CREATE TEXT SEARCH TEMPLATE t (init = i)', 'definition-requirement'])]
    #[TestWith(['CREATE TEXT SEARCH TEMPLATE t (lexize = l, other = o)', 'definition-attribute'])]
    #[TestWith(['CREATE TEXT SEARCH DICTIONARY d (stopwords = english)', 'definition-requirement'])]
    #[TestWith(['CREATE TEXT SEARCH CONFIGURATION c (parser = default, copy = english)', 'definition-requirement'])]
    #[TestWith(['CREATE TEXT SEARCH CONFIGURATION c (foo = english)', 'definition-attribute'])]
    public function testCreateDiagnosesImpossibleDefinitions(string $sql, string $violation): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::from($violation)->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
    }

    public function testParserKeepsTheLastFunction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH PARSER p (start = a, gettoken = g, end = e, lextypes = l, start = b)');
        self::assertInstanceOf(Statement\CreateTextSearchParserStatement::class, $statement);
        self::assertSame(['b'], $statement->start->parts);
    }

    public function testTemplateReadsTheOptionalInit(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH TEMPLATE t (init = i, lexize = l)');
        self::assertInstanceOf(Statement\CreateTextSearchTemplateStatement::class, $statement);
        self::assertSame(['i'], $statement->init?->parts);
    }

    public function testDictionarySeparatesTheTemplateFromItsOptions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH DICTIONARY d (template = simple, "Template" = x, accept)');
        self::assertInstanceOf(Statement\CreateTextSearchDictionaryStatement::class, $statement);
        self::assertSame(['simple'], $statement->template->parts);
        self::assertSame(['Template', 'accept'], array_map(static fn ($option): string => $option->name, $statement->options));
    }

    public function testConfigurationReadsTheParser(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH CONFIGURATION c (parser = s.p)');
        self::assertInstanceOf(Statement\CreateTextSearchConfigurationStatement::class, $statement);
        self::assertSame(['s', 'p'], $statement->parser->parts);
    }

    public function testAlterDictionaryReadsEveryOption(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TEXT SEARCH DICTIONARY d (a = 1, b)');
        self::assertInstanceOf(Statement\AlterTextSearchDictionaryStatement::class, $statement);
        self::assertSame('1', $statement->options[0]->value);
        self::assertNull($statement->options[1]->value);
    }

    public function testAlterConfigurationDistinguishesTheMappingCommands(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $added = $binder->bind('ALTER TEXT SEARCH CONFIGURATION s.c ADD MAPPING FOR word WITH a, b');
        self::assertInstanceOf(Statement\MapTextSearchTokensStatement::class, $added);
        self::assertSame(MappingChange::Add, $added->change);
        self::assertCount(2, $added->dictionaries);
        $replaced = $binder->bind('ALTER TEXT SEARCH CONFIGURATION c ALTER MAPPING REPLACE a WITH b');
        self::assertInstanceOf(Statement\ReplaceTextSearchDictionaryStatement::class, $replaced);
        self::assertNull($replaced->tokenTypes);
        $dropped = $binder->bind('ALTER TEXT SEARCH CONFIGURATION c DROP MAPPING FOR word');
        self::assertInstanceOf(Statement\DropTextSearchMappingStatement::class, $dropped);
        self::assertFalse($dropped->ifExists);
    }

    public function testFunctionRejectsAMissingArgument(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::DefinitionArgument->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEXT SEARCH TEMPLATE t (lexize)');
    }

    public function testOptionKeepsTheTextTheTemplateReads(): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), 'public'));
        $elements = DefinitionElement::list((new DialectParser(Dialect::PostgreSql))->parse('ALTER TEXT SEARCH DICTIONARY d (MaxLen = 007, rules = ON)'), $context);
        self::assertSame('7', TextSearch::option($elements[0], $context)->value);
        self::assertSame('on', TextSearch::option($elements[1], $context)->value);
    }

    #[TestWith(['CREATE TEXT SEARCH TEMPLATE s.t (lexize = a, lexize = b, init = i)', 'CREATE TEXT SEARCH TEMPLATE "s"."t"(INIT = "i", LEXIZE = "b")'])]
    #[TestWith(['CREATE TEXT SEARCH CONFIGURATION c (parser = a, parser = b)', 'CREATE TEXT SEARCH CONFIGURATION "c"(PARSER = "b")'])]
    #[TestWith(['CREATE TEXT SEARCH PARSER b.c (start = s, gettoken = g, end = e, lextypes = l, headline = h)', 'CREATE TEXT SEARCH PARSER "b"."c"(START = "s", GETTOKEN = "g", END = "e", LEXTYPES = "l", HEADLINE = "h")'])]
    #[TestWith(['ALTER TEXT SEARCH DICTIONARY b.c (x = 1, y)', 'ALTER TEXT SEARCH DICTIONARY "b"."c"("x" = \'1\', "y")'])]
    #[TestWith(['ALTER TEXT SEARCH CONFIGURATION b.c ALTER MAPPING FOR word REPLACE d1 WITH d2', 'ALTER TEXT SEARCH CONFIGURATION "b"."c" ALTER MAPPING FOR "word" REPLACE "d1" WITH "d2"'])]
    public function testCreateAndAlterKeepSchemaQualifiedNamesAndTheLastArgument(string $sql, string $expected): void
    {
        self::assertSame($expected, (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql)->toString());
    }

    #[TestWith(['CREATE TEXT SEARCH PARSER a.b.c (start = s, gettoken = g, end = e, lextypes = l)'])]
    #[TestWith(['ALTER TEXT SEARCH DICTIONARY a.b.c (x = 1)'])]
    #[TestWith(['ALTER TEXT SEARCH CONFIGURATION a.b.c DROP MAPPING FOR word'])]
    public function testCreateAndAlterRejectOverQualifiedNames(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::CatalogObjectName->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
    }

    public function testParserBuildsTheStatementFromNamedElements(): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), 'public'));
        $source = (new DialectParser(Dialect::PostgreSql))->parse('CREATE TEXT SEARCH PARSER p (start = a, gettoken = g, end = e, lextypes = l)');
        $origin = new \SqlSemantics\Model\Statement\Origin('s0', $source, Dialect::PostgreSql);
        $elements = DefinitionElement::named(DefinitionElement::list($source, $context), ['start', 'gettoken', 'end', 'lextypes', 'headline'], true);
        $statement = TextSearch::parser($origin, new \SqlSemantics\Model\Relation\QualifiedName(['p']), $elements, $source, $context);
        self::assertSame(['g'], $statement->gettoken->parts);
        self::assertNull($statement->headline);
    }

    public function testTemplateBuildsTheStatementFromNamedElements(): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), 'public'));
        $source = (new DialectParser(Dialect::PostgreSql))->parse('CREATE TEXT SEARCH TEMPLATE t (lexize = l)');
        $origin = new \SqlSemantics\Model\Statement\Origin('s0', $source, Dialect::PostgreSql);
        $statement = TextSearch::template($origin, new \SqlSemantics\Model\Relation\QualifiedName(['t']), DefinitionElement::named(DefinitionElement::list($source, $context), ['init', 'lexize'], true), $source, $context);
        self::assertSame(['l'], $statement->lexize->parts);
        self::assertNull($statement->init);
    }

    public function testDictionaryBuildsTheStatementFromElements(): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), 'public'));
        $source = (new DialectParser(Dialect::PostgreSql))->parse('CREATE TEXT SEARCH DICTIONARY d (template = simple, stopwords = english)');
        $origin = new \SqlSemantics\Model\Statement\Origin('s0', $source, Dialect::PostgreSql);
        $statement = TextSearch::dictionary($origin, new \SqlSemantics\Model\Relation\QualifiedName(['d']), DefinitionElement::list($source, $context), $source, $context);
        self::assertSame(['simple'], $statement->template->parts);
        self::assertSame('stopwords', $statement->options[0]->name);
    }

    public function testConfigurationCopiesAConfiguration(): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), 'public'));
        $source = (new DialectParser(Dialect::PostgreSql))->parse('CREATE TEXT SEARCH CONFIGURATION c (copy = s.o)');
        $origin = new \SqlSemantics\Model\Statement\Origin('s0', $source, Dialect::PostgreSql);
        $statement = TextSearch::configuration($origin, new \SqlSemantics\Model\Relation\QualifiedName(['c']), DefinitionElement::named(DefinitionElement::list($source, $context), ['parser', 'copy'], true), $source, $context);
        self::assertInstanceOf(Statement\CopyTextSearchConfigurationStatement::class, $statement);
    }

    public function testConfigurationRequiresAParserOrACopy(): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), 'public'));
        $source = (new DialectParser(Dialect::PostgreSql))->parse('CREATE TEXT SEARCH CONFIGURATION c (copy = s.o)');
        $origin = new \SqlSemantics\Model\Statement\Origin('s0', $source, Dialect::PostgreSql);
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::DefinitionRequirement->message());
        TextSearch::configuration($origin, new \SqlSemantics\Model\Relation\QualifiedName(['c']), [], $source, $context);
    }

    public function testFunctionReadsTheNamedFunction(): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), 'public'));
        $elements = DefinitionElement::list((new DialectParser(Dialect::PostgreSql))->parse('CREATE TEXT SEARCH TEMPLATE t (lexize = s.l)'), $context);
        self::assertSame(['s', 'l'], TextSearch::function($elements[0], $context)->parts);
    }
}

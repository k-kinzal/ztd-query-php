<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Extensibility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Definition\Extensibility\Extensions;
use SqlSemantics\Binding\TableResolver;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Statement\Definition\Extension as Statement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Extensions::class)]
#[Medium]
final class ExtensionsTest extends TestCase
{
    #[TestWith(['CREATE EXTENSION hstore', 'CREATE EXTENSION "hstore"'])]
    #[TestWith(['CREATE EXTENSION if WITH', 'CREATE EXTENSION "if"'])]
    #[TestWith(['CREATE EXTENSION IF NOT EXISTS e WITH CASCADE VERSION v SCHEMA s', 'CREATE EXTENSION IF NOT EXISTS "e" SCHEMA "s" VERSION \'v\' CASCADE'])]
    public function testCreateReadsEachOptionOnce(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(Statement\CreateExtensionStatement::class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    #[TestWith(['CREATE EXTENSION e CASCADE CASCADE'])]
    #[TestWith(['CREATE EXTENSION e SCHEMA a SCHEMA b'])]
    #[TestWith(["CREATE EXTENSION e FROM '1.0'"])]
    #[TestWith(["CREATE EXTENSION e VERSION ''"])]
    public function testCreateRejectsRepeatedOrUnsupportedOptions(string $sql): void
    {
        try {
            (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
            self::fail('The option list must be diagnosed.');
        } catch (InvalidSql $error) {
            self::assertSame(InputViolation::ExtensionOption, $error->violation);
        }
    }

    public function testUpdateReadsTheTargetVersion(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER EXTENSION e UPDATE TO E'2\\'x'");
        self::assertInstanceOf(Statement\UpdateExtensionStatement::class, $statement);
        self::assertSame("2'x", $statement->version);
        $this->expectException(InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER EXTENSION e UPDATE TO a TO b');
    }

    /**
     * @param class-string<object> $class
     */
    #[TestWith(['ALTER EXTENSION e ADD CAST (int AS text)', Statement\AddExtensionMemberStatement::class, 'ALTER EXTENSION "e" ADD CAST(integer AS text)'])]
    #[TestWith(['ALTER EXTENSION e DROP TRANSFORM FOR int LANGUAGE sql', Statement\DropExtensionMemberStatement::class, 'ALTER EXTENSION "e" DROP TRANSFORM FOR integer LANGUAGE "sql"'])]
    #[TestWith(['ALTER EXTENSION e ADD AGGREGATE agg(*)', Statement\AddExtensionMemberStatement::class, 'ALTER EXTENSION "e" ADD AGGREGATE "agg"(*)'])]
    #[TestWith(['ALTER EXTENSION e DROP TEXT SEARCH PARSER app.p', Statement\DropExtensionMemberStatement::class, 'ALTER EXTENSION "e" DROP TEXT SEARCH PARSER "app"."p"'])]
    public function testMemberReadsTheObjectAddress(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    public function testMemberRejectsAnOverqualifiedName(): void
    {
        $this->expectException(InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER EXTENSION e ADD COLLATION a.b.c');
    }

    public function testLanguageWithoutHandlerInstallsTheExtension(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE OR REPLACE TRUSTED LANGUAGE plperl');
        self::assertInstanceOf(Statement\CreateExtensionStatement::class, $statement);
        self::assertSame('CREATE EXTENSION IF NOT EXISTS "plperl"', $statement->toString());
    }

    public function testLanguageReadsTheHandlers(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE LANGUAGE pl HANDLER a.b INLINE c VALIDATOR d.e');
        self::assertInstanceOf(Statement\CreateLanguageStatement::class, $statement);
        self::assertSame([['a', 'b'], ['c'], ['d', 'e']], [$statement->handler->parts, $statement->inline?->parts, $statement->validator?->parts]);
        $this->expectException(InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE LANGUAGE pl HANDLER a.b.c.d');
    }

    public function testAccessMethodReadsTheTypeAndHandler(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE ACCESS METHOD m TYPE TABLE HANDLER app.h');
        self::assertInstanceOf(Statement\CreateAccessMethodStatement::class, $statement);
        self::assertSame([Statement\AccessMethodKind::Table, ['app', 'h']], [$statement->type, $statement->handler->parts]);
    }

    public function testNameReadsTheDefinedObject(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE ACCESS METHOD "M" TYPE TABLE HANDLER h');
        self::assertInstanceOf(Statement\CreateAccessMethodStatement::class, $statement);
        self::assertSame('M', $statement->name);
    }

    public function testLeadingStopsAtTheName(): void
    {
        self::assertSame(['CREATE', 'EXTENSION', 'IF', 'NOT', 'EXISTS'], Extensions::leading(new Node('CreateExtensionStmt', 0, [new Token(0, 'CREATE', 'CREATE', 0), new Token(0, 'EXTENSION', 'EXTENSION', 7), new Token(0, 'IF_P', 'IF', 17), new Token(0, 'NOT', 'NOT', 20), new Token(0, 'EXISTS', 'EXISTS', 24), new Node('name', 0, [new Token(0, 'IDENT', 'e', 31)]), new Token(0, 'WITH', 'WITH', 33)])));
    }

    public function testFunctionReadsQualifiedHandlerNames(): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), 'public'));
        self::assertSame(['a', 'b'], Extensions::function(new Node('handler_name', 0, [new Token(0, 'IDENT', 'a', 0), new Token(0, '.', '.', 1), new Token(0, 'IDENT', 'B', 2)]), $context)->parts);
    }

    public function testWordDecodesStringsAndIdentifiers(): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), 'public'));
        self::assertSame(["it's", 'v1'], [Extensions::word(new Token(0, 'SCONST', "'it''s'", 0), $context), Extensions::word(new Token(0, 'IDENT', 'V1', 0), $context)]);
    }

    /**
     * @param list<string> $definitions
     */
    #[DataProvider('providerBindReadsEveryExtensionForm')]
    public function testBindReadsEveryExtensionForm(Dialect $dialect, ?string $version, array $definitions, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build(...$definitions)))->bind($sql, strict: false);
        self::assertSame($expected, $statement::class . ' => ' . $statement->toString());
    }

    /**
     * @return iterable<string, array{Dialect, ?string, list<string>, string, string}>
     */
    public static function providerBindReadsEveryExtensionForm(): iterable
    {
        return [
            'create extension if not exists hstore with schema s version \'1.1\' cascade (PostgreSql)' => [Dialect::PostgreSql, null, [], 'create extension if not exists hstore with schema s version \'1.1\' cascade', 'SqlSemantics\\Model\\Statement\\Definition\\Extension\\CreateExtensionStatement => CREATE EXTENSION IF NOT EXISTS "hstore" SCHEMA "s" VERSION \'1.1\' CASCADE'],
            'create extension hstore schema s (PostgreSql)' => [Dialect::PostgreSql, null, [], 'create extension hstore schema s', 'SqlSemantics\\Model\\Statement\\Definition\\Extension\\CreateExtensionStatement => CREATE EXTENSION "hstore" SCHEMA "s"'],
            'CREATE EXTENSION hstore VERSION v1 (PostgreSql)' => [Dialect::PostgreSql, null, [], 'CREATE EXTENSION hstore VERSION v1', 'SqlSemantics\\Model\\Statement\\Definition\\Extension\\CreateExtensionStatement => CREATE EXTENSION "hstore" VERSION \'v1\''],
            'alter extension hstore update to \'2.0\' (PostgreSql)' => [Dialect::PostgreSql, null, [], 'alter extension hstore update to \'2.0\'', 'SqlSemantics\\Model\\Statement\\Definition\\Extension\\UpdateExtensionStatement => ALTER EXTENSION "hstore" UPDATE TO \'2.0\''],
            'ALTER EXTENSION hstore UPDATE (PostgreSql)' => [Dialect::PostgreSql, null, [], 'ALTER EXTENSION hstore UPDATE', 'SqlSemantics\\Model\\Statement\\Definition\\Extension\\UpdateExtensionStatement => ALTER EXTENSION "hstore" UPDATE'],
            'alter extension hstore add function f(int) (PostgreSql)' => [Dialect::PostgreSql, null, [], 'alter extension hstore add function f(int)', 'SqlSemantics\\Model\\Statement\\Definition\\Extension\\AddExtensionMemberStatement => ALTER EXTENSION "hstore" ADD FUNCTION "f"(integer)'],
            'alter extension hstore drop table t (PostgreSql)' => [Dialect::PostgreSql, null, [], 'alter extension hstore drop table t', 'SqlSemantics\\Model\\Statement\\Definition\\Extension\\DropExtensionMemberStatement => ALTER EXTENSION "hstore" DROP TABLE "t"'],
            'create trusted language plx handler h inline i validator v (PostgreSql)' => [Dialect::PostgreSql, null, [], 'create trusted language plx handler h inline i validator v', 'SqlSemantics\\Model\\Statement\\Definition\\Extension\\CreateLanguageStatement => CREATE TRUSTED LANGUAGE "plx" HANDLER "h" INLINE "i" VALIDATOR "v"'],
            'CREATE OR REPLACE LANGUAGE plx HANDLER a.b.h NO VALIDATOR (PostgreSql)' => [Dialect::PostgreSql, null, [], 'CREATE OR REPLACE LANGUAGE plx HANDLER a.b.h NO VALIDATOR', 'SqlSemantics\\Model\\Statement\\Definition\\Extension\\CreateLanguageStatement => CREATE OR REPLACE LANGUAGE "plx" HANDLER "a"."b"."h"'],
            'CREATE LANGUAGE plx (PostgreSql)' => [Dialect::PostgreSql, null, [], 'CREATE LANGUAGE plx', 'SqlSemantics\\Model\\Statement\\Definition\\Extension\\CreateExtensionStatement => CREATE EXTENSION "plx"'],
            'CREATE OR REPLACE PROCEDURAL LANGUAGE plx (PostgreSql)' => [Dialect::PostgreSql, null, [], 'CREATE OR REPLACE PROCEDURAL LANGUAGE plx', 'SqlSemantics\\Model\\Statement\\Definition\\Extension\\CreateExtensionStatement => CREATE EXTENSION IF NOT EXISTS "plx"'],
            'create access method m type table handler h (PostgreSql)' => [Dialect::PostgreSql, null, [], 'create access method m type table handler h', 'SqlSemantics\\Model\\Statement\\Definition\\Extension\\CreateAccessMethodStatement => CREATE ACCESS METHOD "m" TYPE TABLE HANDLER "h"'],
            'create access method m type index handler s.h (PostgreSql)' => [Dialect::PostgreSql, null, [], 'create access method m type index handler s.h', 'SqlSemantics\\Model\\Statement\\Definition\\Extension\\CreateAccessMethodStatement => CREATE ACCESS METHOD "m" TYPE INDEX HANDLER "s"."h"'],
        ];
    }

    #[TestWith(['create extension hstore from \'1.0\''])]
    #[TestWith(['create extension hstore schema a schema b'])]
    #[TestWith(['ALTER EXTENSION hstore UPDATE TO \'1\' TO \'2\''])]
    #[TestWith(['CREATE LANGUAGE plx HANDLER a.b.c.h'])]
    #[TestWith(['CREATE ACCESS METHOD m TYPE INDEX HANDLER a.b.c.h'])]
    public function testBindRejectsRepeatedOptionsAndLongHandlerNames(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $binder->bind($sql, strict: false);
    }
}

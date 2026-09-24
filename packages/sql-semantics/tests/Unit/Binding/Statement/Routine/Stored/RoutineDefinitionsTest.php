<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Routine\Stored;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Routine\Stored\RoutineDefinitions;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\Stored\ExternalRoutineCode;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RoutineDefinitions::class)]
#[Medium]
final class RoutineDefinitionsTest extends TestCase
{
    public function testBindMakesParametersVisibleToTheBody(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('CREATE PROCEDURE p(IN a INT, OUT b INT) SET b = a');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertSame('CREATE PROCEDURE `p`(IN `a` integer, OUT `b` integer) SET `b` = `a`', $statement->toString());
    }

    public function testParametersDiagnosesARepeatedName(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramDeclaration->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE FUNCTION f(a INT, A INT) RETURNS INT RETURN 1', strict: false);
    }

    #[TestWith(["CREATE PROCEDURE p() AS 'x'"])]
    #[TestWith(["CREATE PROCEDURE p() LANGUAGE SQL AS 'x'"])]
    #[TestWith(['CREATE PROCEDURE p() LANGUAGE JAVASCRIPT BEGIN END'])]
    public function testBodyDiagnosesALanguageThatDoesNotMatchTheBody(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramDefinition->message());
        (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-9.1.0'))->build()))->bind($sql, strict: false);
    }

    public function testBodyKeepsTheLanguageOfAStringBody(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind("CREATE PROCEDURE p() LANGUAGE js AS 'x'");
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(ExternalRoutineCode::class, $statement->body);
        self::assertSame('js', $statement->body->language);
    }

    #[TestWith(['$$a$b$$', 'a$b'])]
    #[TestWith(['$t$x$t$', 'x'])]
    #[TestWith(["'a\\\\b'", 'a\\b'])]
    public function testCodeDecodesQuotedAndDollarQuotedStrings(string $text, string $code): void
    {
        self::assertSame($code, RoutineDefinitions::code(new Token(0, str_starts_with($text, '$') ? 'DOLLAR_QUOTED_STRING_SYM' : 'TEXT_STRING', $text, 0), new Identifiers(Dialect::MySql)));
    }

    public function testCodeRoundTripsThroughTheSerializer(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-9.1.0'))->parse('CREATE FUNCTION f() RETURNS INT LANGUAGE JAVASCRIPT AS $$return "\'"$$');
        $string = Tree::outer($tree, ['routine_string'])[0];
        self::assertSame('return "\'"', RoutineDefinitions::code($string->tokens()[0], new Identifiers(Dialect::MySql)));
    }

    /**
     * @return list<array{Dialect, ?string, string, mixed}>
     */
    public static function providerBindWritesEachStoredRoutine(): array
    {
        return [
            [Dialect::MySql, null, 'CREATE FUNCTION IF NOT EXISTS f(a INT) RETURNS INT DETERMINISTIC RETURN a + 1', [\SqlSemantics\Model\Statement\Definition\MySql\Program\CreateFunctionStatement::class, 'CREATE FUNCTION IF NOT EXISTS `f`(`a` integer) RETURNS integer DETERMINISTIC RETURN(`a` + 1)']],
            [Dialect::MySql, null, 'CREATE FUNCTION f(a INT, b INT) RETURNS INT DETERMINISTIC RETURN a + b', [\SqlSemantics\Model\Statement\Definition\MySql\Program\CreateFunctionStatement::class, 'CREATE FUNCTION `f`(`a` integer, `b` integer) RETURNS integer DETERMINISTIC RETURN(`a` + `b`)']],
            [Dialect::MySql, null, 'CREATE PROCEDURE IF NOT EXISTS p(inout x INT, out y INT) SET x = 1', [CreateProcedureStatement::class, 'CREATE PROCEDURE IF NOT EXISTS `p`(INOUT `x` integer, OUT `y` integer) SET `x` = 1']],
            [Dialect::MySql, null, 'CREATE PROCEDURE p(x INT) SET @a = x', [CreateProcedureStatement::class, 'CREATE PROCEDURE `p`(IN `x` integer) SET @`a` = `x`']],
        ];
    }

    #[DataProvider('providerBindWritesEachStoredRoutine')]
    public function testBindWritesEachStoredRoutine(Dialect $dialect, ?string $version, string $sql, mixed $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build()))->bind($sql, strict: false);
        self::assertSame($expected, [$statement::class, $statement->toString()]);
    }

    #[TestWith(['CREATE PROCEDURE p() LEAVE x'])]
    #[TestWith(['CREATE PROCEDURE p() ITERATE x'])]
    public function testBodyRejectsAJumpToAMissingLabel(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $this->expectException(InvalidSql::class);
        $binder->bind($sql, strict: false);
    }
}

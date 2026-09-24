<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Routine\Program\Cursors;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\Body\BlockStatement;
use SqlSemantics\Model\Definition\Routine\Body\Cursor\CursorFetchStatement;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\HandlerDeclaration;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Cursors::class)]
#[Medium]
final class CursorsTest extends TestCase
{
    public function testBindSeesCursorsOfEnclosingBlocksAndHandlers(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; DECLARE EXIT HANDLER FOR SQLEXCEPTION CLOSE c; BEGIN OPEN c; END; END');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(BlockStatement::class, $statement->body);
        self::assertInstanceOf(HandlerDeclaration::class, $statement->body->declarations[1]);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testVariableResolvesParametersAsFetchTargets(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p(OUT v INT) BEGIN DECLARE c CURSOR FOR SELECT 1; FETCH c INTO v; END');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(BlockStatement::class, $statement->body);
        self::assertInstanceOf(CursorFetchStatement::class, $statement->body->statements[0]);
        self::assertSame('v', $statement->body->statements[0]->targets[0]->variable->name);
    }

    public function testVariableDiagnosesAnUndeclaredTarget(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramObject->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; FETCH c INTO v; END', strict: false);
    }

    /**
     * @param list<string> $definitions
     */
    #[DataProvider('providerBindMatchesCursorNamesCaseInsensitively')]
    public function testBindMatchesCursorNamesCaseInsensitively(Dialect $dialect, ?string $version, array $definitions, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build(...$definitions)))->bind($sql, strict: false);
        self::assertSame($expected, $statement->toString());
    }

    /**
     * @return iterable<string, array{Dialect, ?string, list<string>, string, string}>
     */
    public static function providerBindMatchesCursorNamesCaseInsensitively(): iterable
    {
        return [
            'CREATE PROCEDURE p() BEGIN DECLARE a INT; DECLARE Cur CURSOR FOR SELECT 1; OPEN cur; FETCH CUR INTO ... 0' => [Dialect::MySql, null, [], 'CREATE PROCEDURE p() BEGIN DECLARE a INT; DECLARE Cur CURSOR FOR SELECT 1; OPEN cur; FETCH CUR INTO a; CLOSE cUr; END', 'CREATE PROCEDURE `p`() BEGIN DECLARE `a` integer; DECLARE `Cur` CURSOR FOR SELECT 1; OPEN `cur`; FETCH `CUR` INTO `a`; CLOSE `cUr`; END'],
        ];
    }
}

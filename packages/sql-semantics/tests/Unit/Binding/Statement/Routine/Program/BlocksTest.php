<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Routine\Program\Blocks;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\Body\BlockStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Blocks::class)]
#[Medium]
final class BlocksTest extends TestCase
{
    public function testBindAllowsInnerBlocksToRedeclareNames(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('CREATE PROCEDURE p() BEGIN DECLARE a INT; DECLARE a_cond CONDITION FOR 1; BEGIN DECLARE a TEXT; DECLARE a_cond CONDITION FOR 2; SET a = 1; END; END');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(BlockStatement::class, $statement->body);
        self::assertInstanceOf(BlockStatement::class, $statement->body->statements[0]);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    #[TestWith(['BEGIN DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN END; DECLARE c CURSOR FOR SELECT 1; END'])]
    #[TestWith(['BEGIN DECLARE c CURSOR FOR SELECT 1; DECLARE x CONDITION FOR 1; END'])]
    #[TestWith(['BEGIN DECLARE c CURSOR FOR SELECT 1; DECLARE C CURSOR FOR SELECT 2; END'])]
    #[TestWith(['BEGIN DECLARE x CONDITION FOR 1; DECLARE X CONDITION FOR 2; END'])]
    public function testBindDiagnosesDeclarationOrderAndRepeatedNames(string $block): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramDeclaration->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() ' . $block, strict: false);
    }
}

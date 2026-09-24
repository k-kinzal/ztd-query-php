<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Routine\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Routine\Program\ProgramRetrievals;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\Body\EmbeddedStatement;
use SqlSemantics\Model\Definition\Routine\Body\SelectIntoStatement;
use SqlSemantics\Model\Scalar\Reference\LocalVariableReference;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Statement\Retrieval\SelectIntoVariablesStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ProgramRetrievals::class)]
#[Medium]
final class ProgramRetrievalsTest extends TestCase
{
    public function testBindLeavesUserVariableTargetsToSelectInto(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() SELECT 1 INTO @a', strict: false);
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(EmbeddedStatement::class, $statement->body);
        self::assertInstanceOf(SelectIntoVariablesStatement::class, $statement->body->statement);
    }

    public function testBindDiagnosesAWidthMismatch(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::SelectInto->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p(a INT) SELECT 1, 2 INTO a', strict: false);
    }

    public function testTargetReadsAQuotedLocalName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE PROCEDURE p(a INT) SELECT 1 INTO 'a'");
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(SelectIntoStatement::class, $statement->body);
        self::assertInstanceOf(LocalVariableReference::class, $statement->body->targets[0]);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Loading\Copy\Endpoint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\ResultStatement;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Loading\Copy\CopyToStatement;
use SqlSemantics\Model\Statement\Loading\Copy\Endpoint\CopyProgram;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CopyProgram::class)]
#[Medium]
final class CopyProgramTest extends TestCase
{
    public function testKeepsTheWrittenSpelling(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind("COPY t TO PROGRAM 'gzip'");
        self::assertInstanceOf(CopyToStatement::class, $statement);
        self::assertInstanceOf(CopyProgram::class, $statement->destination);
        self::assertSame("'gzip'", $statement->destination->command->text);
    }

    public function testRejectsANumericConstant(): void
    {
        $select = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(ResultStatement::class, $select);
        $number = $select->resultColumns()[0]->expression;
        self::assertInstanceOf(Literal::class, $number);
        $this->expectException(InvalidStructure::class);
        new CopyProgram($number);
    }
}

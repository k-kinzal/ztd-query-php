<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Body;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Body;
use SqlSemantics\Model\Definition\Routine\Body\ProgramStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ProgramStatement::class)]
#[Medium]
final class ProgramStatementTest extends TestCase
{
    /**
     * @param class-string<ProgramStatement> $class
     */
    #[TestWith(['BEGIN END', Body\BlockStatement::class])]
    #[TestWith(['IF 1 THEN DO 1; END IF', Body\IfStatement::class])]
    #[TestWith(['CASE 1 WHEN 1 THEN DO 1; END CASE', Body\SimpleCaseStatement::class])]
    #[TestWith(['CASE WHEN 1 THEN DO 1; END CASE', Body\SearchedCaseStatement::class])]
    #[TestWith(['l: LOOP LEAVE l; END LOOP', Body\LoopStatement::class])]
    #[TestWith(['WHILE 0 DO DO 1; END WHILE', Body\WhileStatement::class])]
    #[TestWith(['REPEAT DO 1; UNTIL 1 END REPEAT', Body\RepeatStatement::class])]
    #[TestWith(['DO 1', Body\EmbeddedStatement::class])]
    public function testClassifiesEveryBodyConstruct(string $body, string $class): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('CREATE PROCEDURE p() ' . $body);
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf($class, $statement->body);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }
}

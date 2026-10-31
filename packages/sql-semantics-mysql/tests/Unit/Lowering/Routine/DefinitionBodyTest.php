<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Routine\DefinitionBody;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;

#[CoversClass(DefinitionBody::class)]
#[Medium]
final class DefinitionBodyTest extends TestCase
{
    public function testSourceKeepsAnExternalBodyLocationForTheLanguageMismatch(): void
    {
        try {
            (new Semantics(Dialect::MySql, 'mysql-8.1.0'))->analyze("CREATE FUNCTION f() RETURNS INT AS 'body'");
            self::fail('The body representation disagrees with the SQL language.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedProgramBody::class, $failure->problem);
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Problem\LanguageBodyMismatch::class, $failure->problem->problem);
            self::assertSame(35, $failure->problem->problem->offset);
        }
    }

    public function testTypeEndsPublishesOnlyCompletedDeclarationBoundaries(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('CREATE FUNCTION f(a INT,b INT) RETURNS INT BEGIN RETURN 1; END');
        self::assertInstanceOf(CreateFunction::class, $statement);
        self::assertSame([23, 29, 42], $statement->definitionSource?->typeEnds);
        self::assertSame(17, $statement->definitionSource->nameEnd);
        self::assertSame('CREATE FUNCTION f(a INT, b INT) RETURNS INT BEGIN RETURN 1; END', $statement->toString());
    }
}

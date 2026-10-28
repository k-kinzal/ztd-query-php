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
    public function testSourceKeepsAnExternalBodyLocationAlongsideTheDeclaration(): void
    {
        $statement = (new Semantics(Dialect::MySql, 'mysql-8.1.0'))->analyze("CREATE FUNCTION f() RETURNS INT AS 'body'");
        self::assertInstanceOf(CreateFunction::class, $statement);
        self::assertSame(35, $statement->definitionSource?->bodyRefusalOffset);
        self::assertSame("CREATE FUNCTION f() RETURNS INT AS 'body'", $statement->toString());
    }

    public function testTypeEndsPublishesOnlyCompletedDeclarationBoundaries(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('CREATE FUNCTION f(a INT,b INT) RETURNS INT BEGIN END');
        self::assertInstanceOf(CreateFunction::class, $statement);
        self::assertSame([23, 29, 42], $statement->definitionSource?->typeEnds);
        self::assertSame(17, $statement->definitionSource->nameEnd);
        self::assertSame('CREATE FUNCTION f(a INT, b INT) RETURNS INT BEGIN END', $statement->toString());
    }
}

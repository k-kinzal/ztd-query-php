<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\ParameterDeclarations;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Prepared\Prepare;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\CreateFunction;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(ParameterDeclarations::class)]
#[Medium]
final class ParameterDeclarationsTest extends TestCase
{
    public function testInfersUndeclaredInPrepareOnly(): void
    {
        $semantics = new Inspection();
        $prepare = $semantics->inspect('PREPARE p (integer) AS SELECT $1')->statement;
        $function = $semantics->inspect('CREATE FUNCTION f(a integer) RETURNS integer RETURN $1')->statement;
        self::assertInstanceOf(Prepare::class, $prepare);
        self::assertInstanceOf(CreateFunction::class, $function);
        self::assertInstanceOf(ParameterDeclarations::class, $prepare->parameters);
        self::assertSame([true, false], [$prepare->parameters->infersUndeclared(), $function->parameters->infersUndeclared()]);
    }
}

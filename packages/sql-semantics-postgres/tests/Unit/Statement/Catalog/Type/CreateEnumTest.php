<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\CreateEnum::class)]
#[Medium]
final class CreateEnumTest extends TestCase
{
    public function testRenderWritesTheLabels(): void
    {
        $out = new \SqlSemantics\Rendering\Output(new \SqlSemantics\Platform\PostgreSql\Rendering\Codec(\SqlSemantics\Contract\GrammarRelease::PostgreSql172));
        (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\CreateEnum(new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Statement\Identifier\Name('mood')]), [new \SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant('sad')]))->render($out);
        self::assertSame('CREATE TYPE mood AS ENUM (\'sad\')', (new \SqlSemantics\Rendering\Lexical())->join($out->pieces()));
    }

    public function testDeriveStatementReportsALongLabel(): void
    {
        self::assertSame('invalid enum label "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TYPE mood AS ENUM (\'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx\')')->facts->diagnostics[0]->message());
    }
}

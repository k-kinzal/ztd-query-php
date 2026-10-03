<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Compilation;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class LiteralArrayLoweringTest extends TestCase
{
    public function testLowerKeepsScalarObservationsAndFallsBackForReferences(): void
    {
        $index = \Tests\Fake\SourceFixture::index('<?php function target(){return [1,2,"x"]; }');
        $body = $index->callable('target');
        self::assertNotNull($body);
        self::assertCount(4, $body->blocks[0]->instructions);
        $constant = $body->blocks[0]->instructions[3]->constant;
        self::assertNotNull($constant);
        self::assertSame([1,2,'x'], $constant->native());
        self::assertCount(4, $body->blocks[0]->instructions);
        $other = \Tests\Fake\SourceFixture::index('<?php function target(&$x){return [&$x,1];}')->callable('target');
        self::assertNotNull($other);
        self::assertContains('array-set', array_column($other->blocks[0]->instructions, 'operation'));
    }

    public function testLiteralNeverEvaluatesApplicationConstants(): void
    {
        $index = \Tests\Fake\SourceFixture::index('<?php');
        $lowering = new \Deriver\Source\Compilation\Lowering($index->builder('fixture.php'), $index, 'target');
        $literal = new \Deriver\Source\Compilation\LiteralArrayLowering($lowering);
        self::assertNull($literal->literal(new \PhpParser\Node\Expr\ConstFetch(new \PhpParser\Node\Name('APP_SECRET'))));
        self::assertNull($literal->literal(new \PhpParser\Node\Expr\Variable('x')));
    }
}

<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Lowerer;

use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Lowerer\LiteralArrayLowering;
use Deriver\Deriver\Lowerer\LoweringScope;
use Deriver\Deriver\Parser\Node\File;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\Sources;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class LiteralArrayLoweringTest extends TestCase
{
    public function testLowerKeepsScalarObservationsAndFallsBackForReferences(): void
    {
        $source = '<?php function target(){return [1,2,"x"]; }';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $body = $index->callable('target');
        self::assertNotNull($body);
        self::assertCount(4, $body->blocks[0]->instructions);
        $constant = $body->blocks[0]->instructions[3]->constant;
        self::assertNotNull($constant);
        self::assertSame([1, 2, 'x'], array_map(static fn ($element) => $element->literal, $constant->operands));
        self::assertCount(4, $body->blocks[0]->instructions);
        $source2 = '<?php function target(&$x){return [&$x,1];}';
        $snapshotId1 = 'test';
        $input1 = new Sources([new Source('fixture.php', $source2)]);
        $profile1 = new TargetProfile();
        $parser1 = new Parser(new ParserConfig($profile1->version));
        $trees1 = array_map(static fn (Source $source3): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source3->path), $source3->contents, $source3->declarationsOnly))), iterator_to_array($input1, false));
        $other = (new Index($snapshotId1, $trees1))->callable('target');
        self::assertNotNull($other);
        self::assertContains('array-set', array_column($other->blocks[0]->instructions, 'operation'));
    }
    public function testLiteralNeverEvaluatesApplicationConstants(): void
    {
        $source = '<?php';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $lowering = new LoweringScope($index->builder('fixture.php'), $index, 'target');
        $literal = new LiteralArrayLowering($lowering);
        self::assertNull($literal->literal(new ConstFetch(new Name('APP_SECRET'))));
        self::assertNull($literal->literal(new Variable('x')));
    }
    public function testObserveRetainsTheSignedExpressionAndItsOperand(): void
    {
        $source = '<?php';
        $snapshotId = 'test';
        $input = new Sources([new Source('fixture.php', $source)]);
        $profile = new TargetProfile();
        $parser = new Parser(new ParserConfig($profile->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input, false));
        $index = new Index($snapshotId, $trees);
        $lowering = new LoweringScope($index->builder('fixture.php'), $index, 'target');
        $literal = new LiteralArrayLowering($lowering);
        $literal->observe(new UnaryMinus(new Int_(3)));
        $blocks = $lowering->graph->finish();
        self::assertSame([3, -3], array_map(static fn ($instruction) => $instruction->constant?->native(), $blocks[0]->instructions));
    }
}

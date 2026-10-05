<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Declaration;

use Deriver\Source\Declaration\PropertyWriteIndex as Subject;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateApi as A;

#[CoversNothing]
final class PropertyWriteIndexTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testOwnersExcludesUnrelatedBodies(): void
    {
        $session = A::session('class C{public $x=1;function write(){$this->x=2;}function read(){return $this->x;}}');
        self::assertSame(['C::write'], (new Subject($session->program))->owners('x'));
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testContainsFindsNestedElementWrites(): void
    {
        $session = A::session('class C{public $x=[];function write(){$this->x["a"]=2;}}');
        $source = $session->program->declarations['c::write'];
        self::assertTrue((new Subject($session->program))->contains($source->node, 'x'));
    }

    public function testMutationRecognizesCompoundAssignments(): void
    {
        $index = \Tests\Fake\SourceFixture::index('<?php function target(){$x+=1;}');
        $node = (new \PhpParser\NodeFinder())->findFirst($index->declarations['target']->node, static fn ($node): bool => $node instanceof \PhpParser\Node\Expr\AssignOp);
        self::assertNotNull($node);
        self::assertTrue((new Subject($index))->mutation($node));
    }

}

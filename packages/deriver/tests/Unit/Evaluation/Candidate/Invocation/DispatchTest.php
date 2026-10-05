<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate\Invocation;

use Deriver\Evaluation\Candidate\Choices;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture as F;

#[CoversNothing]
final class DispatchTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testApplyPreservesReceiverChoicesThroughInheritedMethods(): void
    {
        $engine = F::evaluator('class A{public $name="a";function get(){return $this->name;}}class B extends A{public $name="b";}function target($flag){$repo=$flag?new A:new B;return $repo->get();}');
        $values = array_map(static fn (array $choice) => $choice[0]->native(), (new Choices())->alternatives($engine->returns(F::frame($engine), 64)));
        sort($values);
        self::assertSame(['a', 'b'], $values);
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testTargetKeepsPrivateMethodsLexicallyBound(): void
    {
        $engine = F::evaluator('class A{private function name(){return "private";}function get(){return $this->name();}}class B extends A{function name(){return "child";}}function target(){return (new B)->get();}');
        self::assertSame('private', F::value($engine)->native());
        self::assertArrayNotHasKey('B::name', $engine->context->bodies);
    }

}

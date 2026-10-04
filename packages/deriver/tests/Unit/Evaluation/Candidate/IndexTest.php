<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture;

#[CoversNothing]
final class IndexTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
 */
    public function testGraphDoesNotDeriveACompiledBody(): void
    {
        $e = CandidateFixture::evaluator();
        self::assertNotNull($e->context->index->graph('target'));
        self::assertSame(0, $e->context->bodyExpansions);
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
 */
    public function testCallersKeepsAllMatchingSites(): void
    {
        $e = CandidateFixture::evaluator('function target($x){return $x;}function caller(){target(1);target(2);}');
        self::assertCount(2, $e->context->index->callers('target'));
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
 */
    public function testTargetResolvesAnInheritedDeclaration(): void
    {
        $e = CandidateFixture::evaluator('class A{function f(){return 1;}}class B extends A{function target(){return $this->f();}}');
        $f = CandidateFixture::frame($e, 'B::target');
        self::assertSame('A::f', $e->context->index->target($f->graph, CandidateFixture::instruction($f, 'invoke-method')));
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
 */
    public function testLiteralKeepsAStringName(): void
    {
        $e = CandidateFixture::evaluator('function target(){return "name";}');
        $f = CandidateFixture::frame($e);
        self::assertSame('name', $e->context->index->literal($f->graph, CandidateFixture::instruction($f, 'constant')->result));
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
 */
    public function testClassNameResolvesTheKnownParent(): void
    {
        $e = CandidateFixture::evaluator('class A{}class B extends A{}');
        self::assertSame('A', $e->context->index->className('parent', 'B'));
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
 */
    public function testMethodDoesNotTreatAMissingAncestorAsAKnownMethod(): void
    {
        $e = CandidateFixture::evaluator('class B extends Missing{}');
        self::assertSame('B::unknown', $e->context->index->method('B', 'unknown'));
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
 */
    public function testPropertyDoesNotMixUnrelatedDeclarations(): void
    {
        $e = CandidateFixture::evaluator('class A{public $x=1;}class B{public $x=2;}');
        $a = $e->context->index->property('A', 'x');
        self::assertNotNull($a);
        self::assertSame('A', $a->className);
        $b = $e->context->index->property('B', 'x');
        self::assertNotNull($b);
        self::assertSame('B', $b->className);
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
 */
    public function testTypeRetainsADeclaredReceiver(): void
    {
        $e = CandidateFixture::evaluator('function target(PDO $db){return $db;}');
        $f = CandidateFixture::frame($e);
        self::assertSame('PDO', $e->context->index->type($f->graph, CandidateFixture::instruction($f, 'read')->result));
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
 */
    public function testDeclaredPropertyResolvesItsDeclaringClass(): void
    {
        $e = CandidateFixture::evaluator('class A{public $x=1;function target(){return $this->x;}}');
        $f = CandidateFixture::frame($e, 'A::target');
        $property = $e->context->index->declaredProperty($f->graph, CandidateFixture::instruction($f, 'field-address'));
        self::assertNotNull($property);
        self::assertSame('A', $property->className);
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
 */
    public function testWritesCollectsOnlyTheRequestedDeclaration(): void
    {
        $e = CandidateFixture::evaluator('class A{public $x=1;function set(){$this->x=2;}}class B{public $x=3;function set(){$this->x=4;}}');
        $p = $e->context->index->property('A', 'x');
        self::assertNotNull($p);
        $writes = $e->context->index->writes($p);
        self::assertCount(1, $writes);
        self::assertSame('A::set', $writes[0][0]->body->symbol);
    }
}

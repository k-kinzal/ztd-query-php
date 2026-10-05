<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate\Memory;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class PropertyEffectsTest extends TestCase
{
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testAddressUsesTheObservedAllocation(): void
    {
        self::assertSame(9, \Tests\Fake\CandidateApi::returns('class A{public $x=1;}function target(){$a=new A;$b=$a;$b->x=9;return $a->x;}')->candidates[0]->result);
    }

    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testIncomingUsesTheObservedAllocation(): void
    {
        self::assertSame(7, \Tests\Fake\CandidateApi::returns('class A{public $x=1;function set($x){$this->x=$x;}function get(){return $this->x;}}function target(){$a=new A;$a->set(7);return $a->get();}')->candidates[0]->result);
    }

    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testEffectUsesTheObservedAllocation(): void
    {
        self::assertSame(7, \Tests\Fake\CandidateApi::returns('class A{public $x=1;function set($x){$this->x=$x;}}function target(){$a=new A;$a->set(7);return $a->x;}')->candidates[0]->result);
    }

    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testSelectedUsesTheObservedAllocation(): void
    {
        self::assertSame(7, \Tests\Fake\CandidateApi::returns('class A{public $x=1;function __construct($x){$this->x=$x;}}class B extends A{function __construct(){parent::__construct(7);}}function target(){return (new B)->x;}')->candidates[0]->result);
    }

}

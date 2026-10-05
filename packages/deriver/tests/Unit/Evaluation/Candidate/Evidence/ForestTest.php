<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate\Evidence;

use Deriver\Evaluation\Candidate\Evidence\Forest as Subject;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ForestTest extends TestCase
{
    public function testRootSharesRepeatedOperands(): void
    {
        $value = Term::constant(3);
        $root = (new Subject())->root(Term::array([$value,$value]));
        self::assertSame($root->inputs['operand:0'], $root->inputs['operand:1']);
    }

}

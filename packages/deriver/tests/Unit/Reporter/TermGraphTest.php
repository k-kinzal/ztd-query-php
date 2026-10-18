<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter;

use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use Deriver\Reporter\TermGraph;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class TermGraphTest extends TestCase
{
    public function testEncodeReferencesSharedOperandsWithoutDuplicatingThem(): void
    {
        $input = Term::parameter('name', 'string');
        $term = new Term(TermKind::Concat, operands: [$input, $input]);
        $encoded = (new TermGraph())->encode($term);
        self::assertSame(0, $encoded['root']);
        self::assertCount(2, $encoded['nodes']);
        self::assertSame([1, 1], $encoded['nodes'][0]['operands']);
        self::assertSame('name', $encoded['nodes'][1]['literal']);
    }
}

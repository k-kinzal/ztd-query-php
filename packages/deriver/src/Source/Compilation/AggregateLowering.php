<?php

declare(strict_types=1);

namespace Deriver\Source\Compilation;

use Deriver\Value\Term;
use PhpParser\Node\Expr;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Scalar;

/**
 * Retains ordered array entries, references, unpacking, and interpolated parts.
 * @visibility root
 */
final class AggregateLowering
{
    /**
     * @param Lowering $lowering Callable lowering
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Lowers array and string aggregates.
     * @param Expr\Array_|Scalar\InterpolatedString $node Aggregate expression
     * @return string Aggregate register
     */
    public function lower(Expr\Array_|Scalar\InterpolatedString $node): string
    {
        $g = $this->lowering->graph;
        if ($node instanceof Scalar\InterpolatedString) {
            $result = $g->emit($node, 'constant', constant: Term::constant(''));
            foreach ($node->parts as $part) {
                $value = $part instanceof InterpolatedStringPart ? $g->emit($part, 'constant', constant: Term::constant($part->value)) : $this->lowering->expression($part);
                $result = $g->emit($node, 'binary', [$result, $value], '.');
            }
            return $result;
        }
        $result = $g->emit($node, 'constant', constant: Term::array([]));
        foreach ($node->items as $item) {
            $key = $item->key === null ? '' : $this->lowering->expression($item->key);
            $value = $item->byRef ? $g->emit($item, 'reference', [$this->lowering->location($item->value)]) : $this->lowering->expression($item->value);
            $result = $g->emit($item, $item->unpack ? 'array-unpack' : 'array-set', [$result, $key, $value]);
        }
        return $result;
    }
}

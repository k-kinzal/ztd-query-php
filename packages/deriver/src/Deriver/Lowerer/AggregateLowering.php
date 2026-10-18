<?php

declare(strict_types=1);

namespace Deriver\Deriver\Lowerer;

use Deriver\Deriver\Lowerer\Graph\Literal;
use PhpParser\Node\Expr;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Scalar;

/**
 * Retains ordered array entries, references, unpacking, and interpolated parts.
 * @visibility Deriver\Deriver\Lowerer\Declaration
 * @visibility Deriver\Deriver\Lowerer
 * @visibility Deriver\Deriver\Sources
 */
final class AggregateLowering
{
    /**
     * @param LoweringScope $lowering Callable lowering
     */
    public function __construct(public readonly LoweringScope $lowering)
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
            $result = $g->emit($node, 'constant', constant: Literal::constant(''));
            foreach ($node->parts as $part) {
                $value = $part instanceof InterpolatedStringPart ? $g->emit($part, 'constant', constant: Literal::constant($part->value)) : $this->lowering->expression($part);
                $result = $g->emit($node, 'binary', [$result, $value], '.');
            }
            return $result;
        }
        $literal = (new LiteralArrayLowering($this->lowering))->lower($node);
        if ($literal !== null) {
            return $literal;
        }
        $result = $g->emit($node, 'constant', constant: Literal::array([]));
        foreach ($node->items as $item) {
            $key = $item->key === null ? '' : $this->lowering->expression($item->key);
            $value = $item->byRef ? $g->emit($item, 'reference', [$this->lowering->location($item->value)]) : $this->lowering->expression($item->value);
            $result = $g->emit($item, $item->unpack ? 'array-unpack' : 'array-set', [$result, $key, $value]);
        }
        return $result;
    }
}

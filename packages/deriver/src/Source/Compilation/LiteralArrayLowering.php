<?php

declare(strict_types=1);

namespace Deriver\Source\Compilation;

use Deriver\Value\Term;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;

/**
 * Builds flat, effect-free literal arrays once, retaining every scalar's source observation.
 * @visibility root
 */
final class LiteralArrayLowering
{
    /**
     * @param Lowering $lowering Source expression lowering
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Folds only warning-free keys and scalar literals; other arrays use ordered ordinary transfers.
     * @param Expr\Array_ $node Source array
     * @return string|null Aggregate register or null when an item needs evaluation
     */
    public function lower(Expr\Array_ $node): ?string
    {
        $entries = [];
        $next = 0;
        $hasInteger = false;
        foreach ($node->items as $item) {
            $value = $this->literal($item->value);
            $key = $item->key === null ? Term::constant($next) : $this->literal($item->key);
            if ($item->unpack || $item->byRef || $value === null || $key === null || is_float($key->literal)) {
                return null;
            }
            $raw = $key->literal;
            $normalized = is_bool($raw) ? (int) $raw : ($raw ?? '');
            $key = Term::constant(array_key_first([$normalized => true]));
            if ($key->kind !== 'constant' || !is_int($key->literal) && !is_string($key->literal) || $item->key === null && isset($entries[$next])) {
                return null;
            }
            $entries[$key->literal] = $value;
            if (is_int($key->literal)) {
                $after = $key->literal === PHP_INT_MAX ? PHP_INT_MAX : $key->literal + 1;
                $next = $hasInteger ? max($next, $after) : $after;
                $hasInteger = true;
            }
        }
        foreach ($node->items as $item) {
            if ($item->key !== null) {
                $this->observe($item->key);
            }
            $this->observe($item->value);
        }
        return $this->lowering->graph->emit($node, 'constant', constant: new Term('array', operands: $entries, attributes: ['open' => false, 'next' => $next]));
    }

    /**
     * Keeps both the signed expression and its numeric operand independently observable.
     * @param Expr $node Admitted literal expression
     */
    public function observe(Expr $node): void
    {
        if ($node instanceof Expr\UnaryMinus || $node instanceof Expr\UnaryPlus) {
            $this->lowering->graph->emit($node->expr, 'constant', constant: $this->literal($node->expr));
        }
        $this->lowering->graph->emit($node, 'constant', constant: $this->literal($node));
    }

    /**
     * Recognizes literals without executing application code or resolving application constants.
     * Numeric sign multiplication is warning-free on the required 64-bit host and target.
     * @param Expr $node Source expression
     * @return Term|null Scalar literal, or null for an evaluated expression
     */
    public function literal(Expr $node): ?Term
    {
        if ($node instanceof Scalar\String_ || $node instanceof Scalar\Int_ || $node instanceof Scalar\Float_) {
            return Term::constant($node->value);
        }
        if (($node instanceof Expr\UnaryMinus || $node instanceof Expr\UnaryPlus) && ($node->expr instanceof Scalar\Int_ || $node->expr instanceof Scalar\Float_)) {
            return Term::constant($node->expr->value * ($node instanceof Expr\UnaryMinus ? -1 : 1));
        }
        if ($node instanceof Expr\ConstFetch) {
            return match (strtolower($node->name->toString())) {
                'null' => Term::constant(null), 'true' => Term::constant(true), 'false' => Term::constant(false), default => null,
            };
        }
        return null;
    }
}

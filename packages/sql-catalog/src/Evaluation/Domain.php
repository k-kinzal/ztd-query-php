<?php

declare(strict_types=1);

namespace SqlCatalog\Evaluation;

use SqlCatalog\Text\Origin;
use SqlCatalog\Text\TextGeneralization;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

/**
 * Every value an expression may take, over-approximated.
 *
 * A domain is a bounded set of alternatives. Keeping the set means a query
 * built from an enum-typed parameter is reported as the handful of statements
 * it can actually be, instead of one statement with a gap. When the set would
 * grow past the bound it is generalized into a single shape that still covers
 * all of it, so the approximation only ever widens.
 *
 * @visibility root
 */
final class Domain
{
    private ?string $signature = null;

    /**
     * How many alternatives a domain keeps before generalizing them into one shape.
     */
    public const MAX_TERMS = 12;

    /**
     * @param list<Term> $terms Alternatives, deduplicated and non-empty
     * @param bool $widened Whether alternatives were merged away to stay within the bound
     * @param bool $combined Whether alternatives came from pairing parts that vary independently
     */
    private function __construct(
        public readonly array $terms,
        public readonly bool $widened,
        public readonly bool $combined = false,
    ) {
    }

    /**
     * The domain holding exactly one alternative.
     */
    public static function of(Term $term): self
    {
        return new self([$term], false);
    }

    /**
     * The domain holding one resolved scalar.
     */
    public static function literal(string|int|float|bool|null $value): self
    {
        return self::of(new LiteralTerm($value));
    }

    /**
     * The domain of values known only by type and origin.
     */
    public static function opaque(TypeShape $type, Origin $origin, ?string $expression = null): self
    {
        return self::of(new OpaqueTerm($type, $origin, $expression));
    }

    /**
     * The domain of a value nothing is known about.
     */
    public static function unknown(?string $expression = null): self
    {
        return self::of(OpaqueTerm::unresolved($expression));
    }

    /**
     * The domain built from a set of alternatives, deduplicated and bounded.
     *
     * @param list<Term> $terms
     */
    public static function fromTerms(array $terms, bool $widened = false, bool $combined = false): self
    {
        $unique = [];
        foreach ($terms as $term) {
            $unique[$term->signature()] = $term;
        }
        $kept = array_values($unique);

        if ($kept === []) {
            return self::unknown();
        }
        if (count($kept) <= self::MAX_TERMS) {
            return new self($kept, $widened, $combined);
        }

        return new self([self::generalize($kept, Origin::Branch)], true, $combined);
    }

    /**
     * The single term that covers every given alternative.
     *
     * @param list<Term> $terms
     */
    public static function generalize(array $terms, Origin $origin): Term
    {
        $patterns = [];
        foreach ($terms as $term) {
            $patterns[] = $term->toPattern();
        }

        return new PatternTerm((new TextGeneralization($origin))->mergeAll($patterns));
    }

    /**
     * The domain admitting every value either domain admits.
     */
    public function union(self $other): self
    {
        return self::fromTerms(
            array_merge($this->terms, $other->terms),
            $this->widened || $other->widened,
            $this->combined || $other->combined,
        );
    }

    /**
     * The domain of the strings produced by writing this domain then the other.
     *
     * Pairing two sides that each hold several alternatives produces every
     * combination of them, and nothing here can tell which combinations the
     * program can actually reach. The result is marked as combined so that a
     * report can say the alternatives may be wider than the code allows.
     */
    public function concat(self $other): self
    {
        $terms = [];
        foreach ($this->terms as $left) {
            foreach ($other->terms as $right) {
                $terms[] = self::asTerm($left->toPattern()->concat($right->toPattern()));
            }
        }
        $paired = count($this->terms) > 1 && count($other->terms) > 1;

        return self::fromTerms($terms, $this->widened || $other->widened, $this->combined || $other->combined || $paired);
    }

    /**
     * The term standing for a pattern, collapsed to a literal when fully resolved.
     */
    public static function asTerm(TextPattern $pattern): Term
    {
        $text = $pattern->text();

        return $text === null ? new PatternTerm($pattern) : new LiteralTerm($text);
    }

    /**
     * The domain narrowed to a single shape covering all of its alternatives.
     */
    public function collapse(Origin $origin): self
    {
        if (count($this->terms) === 1) {
            return $this;
        }

        return new self([self::generalize($this->terms, $origin)], true, $this->combined);
    }

    /**
     * The one resolved scalar this domain holds, or null when it holds anything else.
     */
    public function soleLiteral(): ?LiteralTerm
    {
        $term = count($this->terms) === 1 ? $this->terms[0] : null;

        return $term instanceof LiteralTerm ? $term : null;
    }

    /**
     * The one array this domain holds, or null when it holds anything else.
     */
    public function soleArray(): ?ArrayTerm
    {
        $term = count($this->terms) === 1 ? $this->terms[0] : null;

        return $term instanceof ArrayTerm ? $term : null;
    }

    /**
     * The one object this domain holds, or null when it holds anything else.
     */
    public function soleObject(): ?ObjectTerm
    {
        $term = count($this->terms) === 1 ? $this->terms[0] : null;

        return $term instanceof ObjectTerm ? $term : null;
    }

    /**
     * Labels opaque alternatives with the PHP variable read at their use site.
     *
     * Provenance, resolved fragments, comparison signatures and domain flags
     * remain unchanged; the name is only an annotation for report readers.
     */
    public function withVariable(string $variable): self
    {
        if (array_filter($this->terms, static fn (Term $term): bool => $term instanceof OpaqueTerm) === []) {
            return $this;
        }

        return new self(
            array_map(
                static fn (Term $term): Term => $term instanceof OpaqueTerm
                    ? new OpaqueTerm($term->type, $term->origin, $term->expression, $variable)
                    : $term,
                $this->terms,
            ),
            $this->widened,
            $this->combined,
        );
    }

    /**
     * The alternatives written as string patterns.
     *
     * @return list<TextPattern>
     */
    public function patterns(): array
    {
        $patterns = [];
        foreach ($this->terms as $term) {
            $patterns[] = $term->toPattern();
        }

        return $patterns;
    }

    /**
     * Whether every alternative resolves to an exact string.
     */
    public function isExact(): bool
    {
        foreach ($this->terms as $term) {
            if (!$term->toPattern()->isExact()) {
                return false;
            }
        }

        return true;
    }

    /**
     * The static type covering every alternative.
     */
    public function type(): TypeShape
    {
        $type = $this->terms[0]->type();
        foreach ($this->terms as $term) {
            $type = $type->union($term->type());
        }

        return $type;
    }

    /**
     * A canonical string used to compare domains.
     */
    public function signature(): string
    {
        if ($this->signature !== null) {
            return $this->signature;
        }
        $parts = [];
        foreach ($this->terms as $term) {
            $parts[] = $term->signature();
        }
        sort($parts);

        return $this->signature = implode('|', $parts);
    }

    /**
     * Whether the two domains describe the same set of values.
     */
    public function equals(self $other): bool
    {
        return $this->signature() === $other->signature();
    }
}

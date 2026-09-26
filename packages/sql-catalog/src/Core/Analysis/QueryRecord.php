<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Analysis;

use SqlCatalog\Core\Catalog\CallSite;
use SqlCatalog\Core\Evaluation\Domain;
use SqlCatalog\Core\Sql\StatementKind;
use SqlCatalog\Core\Text\TextPattern;

/**
 * One statement found at a call site, while values may still be bound to it.
 *
 * A prepared statement is recorded when it is prepared and completed when it is
 * executed, so the record stays open between the two.
 *
 * @visibility root
 */
final class QueryRecord
{
    /**
     * @var array<int, Domain>
     */
    private array $positional = [];

    /**
     * @var array<string, Domain>
     */
    private array $named = [];

    private bool $bound = false;

    private bool $truncated;

    /**
     * @param CallSite $site Where the statement is issued
     * @param string $siteKey What tells this call apart from every other, including one on the same line
     * @param TextPattern $pattern The statement text as far as it resolved
     * @param StatementKind|null $kind The kind the call implies, when it implies one
     * @param bool $combined Whether the statement came from pairing parts that vary independently
     * @param list<string> $through The path taken to this reading, from the body the walk started in, outermost first
     * @param bool $truncated Whether a bound cut the search short of every way the statement can be
     */
    public function __construct(
        public readonly CallSite $site,
        public readonly string $siteKey,
        public readonly TextPattern $pattern,
        public readonly ?StatementKind $kind = null,
        public bool $combined = false,
        public readonly array $through = [],
        bool $truncated = false,
    ) {
        $this->truncated = $truncated;
    }

    /**
     * Whether a bound cut the search short of every way the statement can be.
     */
    public function isTruncated(): bool
    {
        return $this->truncated;
    }

    /**
     * Binds values given as one array, by position and by name.
     *
     * Named keys are written both with and without their leading colon, so the
     * key is normalized to the name the statement itself uses.
     *
     * @param array<int, Domain> $positional
     * @param array<string, Domain> $named
     */
    public function bind(array $positional, array $named): void
    {
        $this->positional = $positional;
        foreach ($named as $key => $value) {
            $this->named[ltrim($key, ':')] = $value;
        }
        $this->bound = true;
    }

    /**
     * Binds one value, named or positional depending on how it is keyed.
     */
    public function bindOne(string|int|null $key, Domain $value): void
    {
        $this->bound = true;
        if (is_int($key)) {
            $this->positional[$key - 1] = $value;

            return;
        }
        if ($key === null) {
            $this->positional[] = $value;

            return;
        }
        $this->named[ltrim($key, ':')] = $value;
    }

    /**
     * Takes on everything another reading of the same statement bound.
     *
     * The same call is reached both on its own and through the callers that
     * lead to it. Each reading is one way the statement can be bound, so the
     * record keeps the union of them rather than whichever was seen last.
     */
    public function absorb(self $other): void
    {
        foreach ($other->positional as $position => $value) {
            $held = $this->positional[$position] ?? null;
            $this->positional[$position] = $held === null ? $value : $held->union($value);
        }
        foreach ($other->named as $name => $value) {
            $held = $this->named[$name] ?? null;
            $this->named[$name] = $held === null ? $value : $held->union($value);
        }
        $this->bound = $this->bound || $other->bound;
        $this->truncated = $this->truncated || $other->truncated;
        $this->combined = $this->combined || $other->combined;
    }

    /**
     * The values bound by position, keyed by position counting from zero, in order.
     *
     * A position nothing was bound to is absent rather than closed up, so a
     * value bound to the second placeholder alone stays with the second
     * placeholder.
     *
     * @return array<int, Domain>
     */
    public function positional(): array
    {
        ksort($this->positional);

        return $this->positional;
    }

    /**
     * The values bound by name.
     *
     * @return array<string, Domain>
     */
    public function named(): array
    {
        return $this->named;
    }

    /**
     * Whether anything was ever bound to the statement.
     */
    public function isBound(): bool
    {
        return $this->bound;
    }
}

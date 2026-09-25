<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Catalog;

use SqlCatalog\Core\Evaluation\Domain;
use SqlCatalog\Core\Evaluation\LiteralTerm;
use SqlCatalog\Core\Evaluation\OpaqueTerm;
use SqlCatalog\Core\Text\Origin;

/**
 * What a bound value can be, reported in the form the catalog stores.
 *
 * A value that resolves statically is reported as the literals it can take, so
 * a parameter typed as a backed enum becomes the union of that enum's values
 * rather than a bare `string`. A value the analyzer could not follow is
 * reported as its static type and where it came from.
 *
 * @visibility root
 */
final class ValueDomain
{
    /**
     * @param string $type The static type, written the way PHP writes it
     * @param list<string|int|float|bool|null> $values The values it can take, when they are known
     * @param bool $exhaustive Whether the listed values are all of them
     * @param list<string> $origins Where unresolved parts of the value come from
     */
    public function __construct(
        public readonly string $type,
        public readonly array $values,
        public readonly bool $exhaustive,
        public readonly array $origins,
    ) {
    }

    /**
     * The report of what an evaluated domain can hold.
     */
    public static function fromDomain(Domain $domain): self
    {
        $values = [];
        $origins = [];
        $exhaustive = !$domain->widened;

        foreach ($domain->terms as $term) {
            if ($term instanceof LiteralTerm) {
                $values[] = $term->value;
                continue;
            }
            $exhaustive = false;
            $origins[] = $term instanceof OpaqueTerm ? $term->origin->value : Origin::Unresolved->value;
        }

        return new self(
            $domain->type()->display(),
            $exhaustive ? $values : [],
            $exhaustive,
            array_values(array_unique($origins)),
        );
    }

    /**
     * Whether the value is pinned down to a known set of alternatives.
     */
    public function isResolved(): bool
    {
        return $this->exhaustive && $this->values !== [];
    }

    /**
     * Whether a runtime value is one the domain admits.
     *
     * An unresolved domain admits everything, which is what makes it a sound
     * over-approximation rather than a claim about the value.
     */
    public function admits(string|int|float|bool|null $value): bool
    {
        if (!$this->isResolved()) {
            return true;
        }
        foreach ($this->values as $candidate) {
            if ($candidate === $value) {
                return true;
            }
            if (is_scalar($candidate) && is_scalar($value) && (string) $candidate === (string) $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * The domain written for a reader.
     */
    public function display(): string
    {
        if (!$this->isResolved()) {
            return $this->type;
        }
        $written = [];
        foreach ($this->values as $value) {
            $written[] = is_string($value) ? "'" . $value . "'" : var_export($value, true);
        }

        return implode('|', $written);
    }
}

<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

use RuntimeException;

/**
 * Reports that the checked-in terminal catalog cannot be trusted.
 *
 * The catalog is generated from an upstream lexer and committed as a resource
 * file, so a malformed one is a property of that file rather than of the code
 * reading it. Every way it can be wrong is named here, which is what lets a
 * failure say which field or which terminal is at fault instead of repeating
 * one sentence at thirty different places.
 */
final class LexicalCatalogException extends RuntimeException
{
    /**
     * Reports a top-level field of the wrong shape.
     *
     * @param string $field Dotted path of the offending field, e.g. "source.engine"
     *
     * @return self Exception naming the field
     */
    public static function malformedShape(string $field): self
    {
        return new self("Invalid upstream lexical catalog shape. Field: {$field}");
    }

    /**
     * Reports a terminal catalog whose keys or entries are not what they must be.
     *
     * @return self Exception describing the terminal catalog
     */
    public static function malformedTerminalCatalog(): self
    {
        return new self('Invalid upstream lexical terminal catalog.');
    }

    /**
     * Reports a witness that does not describe a lexing example.
     *
     * @param string $terminal Terminal the witness was listed under
     *
     * @return self Exception naming the terminal
     */
    public static function malformedWitness(string $terminal): self
    {
        return new self("Invalid terminal witness: {$terminal}");
    }

    /**
     * Reports an exclusion written without a terminal name or a reason.
     *
     * @return self Exception describing the requirement
     */
    public static function malformedExclusion(): self
    {
        return new self('Terminal exclusions require string terminals and nonempty reasons.');
    }

    /**
     * Reports coverage units that are not a plain list of names.
     *
     * @return self Exception describing the requirement
     */
    public static function malformedCoverageUnits(): self
    {
        return new self('Coverage units must be a list of strings.');
    }

    /**
     * Reports a coverage witness written without a unit or an identifier.
     *
     * @return self Exception describing the requirement
     */
    public static function malformedCoverageWitness(): self
    {
        return new self('Coverage witnesses require string units and identifiers.');
    }

    /**
     * Reports a coverage exclusion written without a unit or a reason.
     *
     * @return self Exception describing the requirement
     */
    public static function malformedCoverageExclusion(): self
    {
        return new self('Coverage exclusions require string units and nonempty reasons.');
    }

    /**
     * Reports the same coverage unit listed more than once.
     *
     * @return self Exception describing the duplication
     */
    public static function duplicateCoverageUnits(): self
    {
        return new self('Upstream lexer coverage units must be unique.');
    }

    /**
     * Reports a coverage unit claimed as both witnessed and excluded.
     *
     * @return self Exception describing the overlap
     */
    public static function overlappingClassification(): self
    {
        return new self('Upstream lexer coverage units cannot be both witnessed and excluded.');
    }

    /**
     * Reports coverage units that are neither witnessed nor excluded.
     *
     * @return self Exception describing the gap
     */
    public static function incompleteClassification(): self
    {
        return new self('Upstream lexer coverage units are not completely classified.');
    }

    /**
     * Reports a terminal that is both catalogued and excluded.
     *
     * @return self Exception describing the contradiction
     */
    public static function terminalIsAlsoExcluded(): self
    {
        return new self('Terminal catalog and exclusions must be disjoint string keys.');
    }

    /**
     * Reports a catalogued terminal with no witnesses to show for it.
     *
     * @param string $terminal Terminal that carries no witness
     *
     * @return self Exception naming the terminal
     */
    public static function emptyTerminal(string $terminal): self
    {
        return new self("Terminal catalog is empty: {$terminal}");
    }

    /**
     * Reports the same witness identifier used twice.
     *
     * @param string $id Identifier that appears more than once
     *
     * @return self Exception naming the identifier
     */
    public static function duplicateWitnessId(string $id): self
    {
        return new self("Duplicate terminal witness identifier: {$id}");
    }

    /**
     * Reports a witness that claims a coverage unit the catalog does not list.
     *
     * @param string $unit Unit the witness referred to
     *
     * @return self Exception naming the unit
     */
    public static function unknownCoverageUnit(string $unit): self
    {
        return new self("Terminal witness references an unknown coverage unit: {$unit}");
    }

    /**
     * Reports a coverage unit witnessed by an identifier no witness carries.
     *
     * @param string $unit Unit whose witness is missing
     *
     * @return self Exception naming the unit
     */
    public static function unknownWitness(string $unit): self
    {
        return new self("Coverage unit references an unknown witness: {$unit}");
    }

    /**
     * Reports a coverage unit whose named witness does not cover it.
     *
     * @param string $unit Unit the witness fails to reference back
     *
     * @return self Exception naming the unit
     */
    public static function witnessDoesNotCoverItsUnit(string $unit): self
    {
        return new self("Coverage witness does not reference its unit: {$unit}");
    }

    /**
     * Reports grammar terminals the catalog neither witnesses nor excludes.
     *
     * @param list<string> $terminals Terminals with no classification
     *
     * @return self Exception listing the terminals
     */
    public static function missingTerminals(array $terminals): self
    {
        return new self(sprintf(
            'Upstream lexer catalog is missing grammar terminals: %s',
            implode(', ', $terminals),
        ));
    }
}

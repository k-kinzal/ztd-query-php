<?php

declare(strict_types=1);

namespace SqlCatalog\Catalog;

use SqlCatalog\Text\Origin;
use SqlCatalog\Text\TextPattern;

/**
 * How far the analyzer got with a statement, and why it got no further.
 *
 * A statement that is not pinned down is not simply "unknown". Having followed
 * a value all the way to a request parameter is a different answer from having
 * run out of budget, and both are different from not modelling the dependency
 * at all. Reporting which one it is separates what the program does from what
 * the analyzer managed to establish.
 *
 * @visibility root
 */
enum Resolution: string
{
    case Resolved = 'resolved';
    case ExternalInput = 'external-input';
    case IncompleteModel = 'incomplete-model';
    case Incomplete = 'incomplete';

    /**
     * How far the analyzer got with a statement of this shape.
     */
    public static function of(TextPattern $pattern): self
    {
        $origins = [];
        foreach ($pattern->holes() as $hole) {
            $origins[] = $hole->origin;
        }
        if ($origins === []) {
            return self::Resolved;
        }
        if (in_array(Origin::Budget, $origins, true)) {
            return self::Incomplete;
        }

        return in_array(Origin::External, $origins, true) ? self::ExternalInput : self::IncompleteModel;
    }

    /**
     * Whether the statement text is pinned down.
     */
    public function isResolved(): bool
    {
        return $this === self::Resolved;
    }

    /**
     * Whether the analyzer closed every dependency it set out to follow.
     *
     * Reaching runtime input closes the search: the trail was followed to its
     * end and the string simply is not fixed. Running out of budget, or not
     * modelling a dependency, leaves the search open.
     */
    public function isClosed(): bool
    {
        return match ($this) {
            self::Resolved, self::ExternalInput => true,
            self::IncompleteModel, self::Incomplete => false,
        };
    }

    /**
     * What the analyzer can say about the statement, in one line.
     */
    public function describe(): string
    {
        return match ($this) {
            self::Resolved => 'The statement text is fully determined.',
            self::ExternalInput => 'The values were followed to runtime input, so the text cannot be fixed.',
            self::IncompleteModel => 'A dependency the analyzer does not model was reached.',
            self::Incomplete => 'A cycle or an analysis budget stopped the search before it closed.',
        };
    }
}

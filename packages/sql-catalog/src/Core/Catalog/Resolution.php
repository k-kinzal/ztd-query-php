<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Catalog;

use SqlCatalog\Core\Text\Origin;
use SqlCatalog\Core\Text\TextPattern;

/**
 * How far the analyzer got with a statement, and why it got no further.
 *
 * A statement that is not pinned down is not simply "unknown". Having followed
 * a value all the way to a request parameter is a different answer from having
 * run out of budget, both are different from not modelling the dependency at
 * all, and all three are different from never having looked at the call.
 * Reporting which one it is separates what the program does from what the
 * analyzer managed to establish.
 *
 * @visibility root
 */
enum Resolution: string
{
    case Resolved = 'resolved';
    case ExternalInput = 'external-input';
    case IncompleteModel = 'incomplete-model';
    case Incomplete = 'incomplete';
    case NotAnalyzed = 'not-analyzed';

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
        if (in_array(Origin::Unreached, $origins, true)) {
            return self::NotAnalyzed;
        }
        if (in_array(Origin::Budget, $origins, true)) {
            return self::Incomplete;
        }

        foreach ($origins as $origin) {
            if ($origin !== Origin::External) {
                return self::IncompleteModel;
            }
        }

        return self::ExternalInput;
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
            self::IncompleteModel, self::Incomplete, self::NotAnalyzed => false,
        };
    }

    /**
     * Whether a statement was read from the call at all.
     *
     * A budget and a call nothing was read from both leave the text open for
     * a reason about the analyzer rather than about the program, so what is
     * left is not a statement with values spliced into it and must not be
     * reported as one.
     */
    public function wasRead(): bool
    {
        return match ($this) {
            self::Resolved, self::ExternalInput, self::IncompleteModel => true,
            self::Incomplete, self::NotAnalyzed => false,
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
            self::NotAnalyzed => 'The call was found but never examined, so nothing was read from it.',
        };
    }
}

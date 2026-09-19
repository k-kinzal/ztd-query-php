<?php

declare(strict_types=1);

namespace SqlCatalog\Catalog;

use SqlCatalog\Text\TextPattern;

/**
 * The identifier a catalogued statement keeps across runs.
 *
 * The identifier is built from the file, the enclosing function, the database
 * call and the statement shape, and deliberately not from the line number, so
 * that editing unrelated lines of a file does not renumber its catalog. That is
 * what makes two catalogs comparable across a pull request.
 *
 * @visibility root
 */
final class EntryIdentity
{
    /**
     * How many hexadecimal characters an identifier keeps.
     */
    public const LENGTH = 12;

    /**
     * The identifier of a statement issued at the given site.
     *
     * Two calls in the same function can produce statements the catalog cannot
     * tell apart — two unreached calls, say, whose text is entirely unknown.
     * The occurrence separates them without bringing the line number back in,
     * so they stay distinct and still survive an edit above them.
     *
     * @param int $occurrence Which of the otherwise identical statements this is, counting from zero
     */
    public function compute(CallSite $site, TextPattern $pattern, int $occurrence = 0): string
    {
        $material = implode("\x1f", [$site->file, $site->function, $site->sink, $pattern->signature()]);
        if ($occurrence > 0) {
            $material .= "\x1f" . $occurrence;
        }

        return substr(hash('sha256', $material), 0, self::LENGTH);
    }
}

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
     */
    public function compute(CallSite $site, TextPattern $pattern): string
    {
        $material = implode("\x1f", [$site->file, $site->function, $site->sink, $pattern->signature()]);

        return substr(hash('sha256', $material), 0, self::LENGTH);
    }
}

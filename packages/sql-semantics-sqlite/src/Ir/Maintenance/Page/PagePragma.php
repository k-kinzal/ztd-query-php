<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Page;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A page size, current page count or maximum page count operation.
 *
 * @visibility public
 * @example Counting the pages of the main database
 *     $field = \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Page\PagePragmaKind::Count;
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Page\PagePragma($field))->toString() // => 'PRAGMA "main".page_count'
 */
final class PagePragma implements SemanticsIR
{
    use Snapshot;

    /**
     * Fixes the pager field, exact physical schema and optional typed setting.
     * @throws InvalidConstruction When a setting does not belong to its native operation
     */
    public function __construct(
        public readonly PagePragmaKind $kind,
        public readonly Identifier $schema = new Identifier('main'),
        public readonly RequestedPageSize|PageLimit|null $value = null,
    ) {
        Check::input(match ($kind) {
            PagePragmaKind::Count => $value === null,
            PagePragmaKind::Size => $value === null || $value instanceof RequestedPageSize,
            PagePragmaKind::MaximumCount => $value === null || $value instanceof PageLimit,
        }, 'The pager setting must match its native operation.');
    }

    /**
     * Identifies the pinned SQLite language profile.
     */
    public function profileId(): string
    {
        return 'sqlite-3.47.2/utf8/short-column-names/numbered-parameters';
    }

    /**
     * Emits the requested operation and preserves the presence of a size write.
     */
    public function toString(): string
    {
        $value = $this->value;
        $argument = match (true) {
            $value === null => '',
            $value instanceof RequestedPageSize => '(' . $value->bytes . ')',
            $value instanceof PageLimit => '(' . $value->decimal . ')',
        };

        return 'PRAGMA ' . $this->schema->quoted() . '.' . $this->kind->value . $argument;
    }
}

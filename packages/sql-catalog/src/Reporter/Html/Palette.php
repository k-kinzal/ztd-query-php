<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html;

use SqlCatalog\Catalog\Resolution;
use SqlCatalog\Catalog\Severity;

/**
 * Which hue each fact about a statement is written in.
 *
 * What a statement does and what the analyzer managed to establish about it
 * are two different things, and the report keeps them apart by hue. The kind
 * carries an identity hue because it is a property of the statement; the
 * resolution and the findings carry state hues because they are judgements
 * about the reading.
 *
 * @visibility root
 */
final class Palette
{
    /**
     * The identity hue a statement of that kind is written in.
     */
    public function kind(string $kind): string
    {
        return 'k-' . $this->kindGroup($kind);
    }

    /**
     * The group a kind is coloured as, which is also how the report sections a table's statements.
     */
    public function kindGroup(string $kind): string
    {
        return match ($kind) {
            'select' => 'select',
            'insert', 'replace', 'merge' => 'insert',
            'update' => 'update',
            'delete' => 'delete',
            'create', 'alter', 'drop', 'truncate' => 'schema',
            default => 'other',
        };
    }

    /**
     * The state hue a resolution is written in.
     */
    public function resolution(Resolution $resolution): string
    {
        return match ($resolution) {
            Resolution::Resolved => 's-ok',
            Resolution::ExternalInput => 's-danger',
            Resolution::IncompleteModel, Resolution::Incomplete => 's-warn',
            Resolution::NotAnalyzed => 's-neutral',
        };
    }

    /**
     * The state hue a severity is written in.
     */
    public function severity(Severity $severity): string
    {
        return match ($severity) {
            Severity::High => 's-danger',
            Severity::Medium => 's-warn',
            Severity::Low, Severity::Info => 's-neutral',
        };
    }

    /**
     * The bar tint that goes with a chip role.
     */
    public function bar(string $chipRole): string
    {
        return 'bar-' . substr($chipRole, 2);
    }
}

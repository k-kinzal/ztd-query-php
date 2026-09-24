<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html;

use SqlCatalog\Catalog\Resolution;
use SqlCatalog\Catalog\Severity;

/**
 * Which hue each fact about a statement is written in.
 *
 * The hues are doc-ui's tones. What a statement does and what the analyzer
 * managed to establish about it are two different things, and the report
 * keeps them apart by hue. The kind carries an identity tone — blue, teal,
 * violet, pink, indigo, slate — because it is a property of the statement;
 * the resolution and the findings carry state tones — ok, warn, danger,
 * neutral — because they are judgements about the reading. A search the
 * analyzer left open is the common case in a real application, so it is
 * written quietly, as an outline, and the loud tones are kept for what is
 * rare and worth a look: input from outside the program, and a finding of
 * some severity.
 *
 * @visibility root
 */
final class Palette
{
    /**
     * The quiet writing of a state that is ordinary: an outline with no tint.
     */
    public const OPEN = 'chip-ghost';

    /**
     * The identity tone a statement of that kind is written in.
     */
    public function kind(string $kind): string
    {
        return match ($this->kindGroup($kind)) {
            'select' => 'tone-blue',
            'insert' => 'tone-teal',
            'update' => 'tone-violet',
            'delete' => 'tone-pink',
            'schema' => 'tone-indigo',
            default => 'tone-slate',
        };
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
     * The state tone a resolution is written in.
     */
    public function resolution(Resolution $resolution): string
    {
        return match ($resolution) {
            Resolution::Resolved => 'tone-ok',
            Resolution::ExternalInput => 'tone-danger',
            Resolution::IncompleteModel, Resolution::Incomplete => self::OPEN,
            Resolution::NotAnalyzed => 'tone-neutral',
        };
    }

    /**
     * The state tone a severity is written in.
     */
    public function severity(Severity $severity): string
    {
        return match ($severity) {
            Severity::High => 'tone-danger',
            Severity::Medium => 'tone-warn',
            Severity::Low, Severity::Info => 'tone-neutral',
        };
    }

    /**
     * The class a segment of a meter carries for a chip role.
     *
     * A tone is a tone on a meter as on a chip; the quiet outline of an open
     * search is a hatched segment, which is how doc-ui draws what is not
     * settled.
     */
    public function bar(string $chipRole): string
    {
        return $chipRole === self::OPEN ? 'is-open' : $chipRole;
    }
}

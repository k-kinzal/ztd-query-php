<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Type;

use SqlParser\Parser\Node;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\TypeNotices;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Type\Decimal;
use SqlSemantics\Platform\MySql\Statement\Type\Floating;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;

/**
 * Interprets completed numeric and character-type attributes before later malformed input.
 *
 * The attribute is lowered to its ordinary typed value. Full derivation and
 * prefix lowering then use the same ordered warning rule. Unknown charset
 * names can abort before the enclosing attribute completes.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxNotices
{
    /**
     * Reads the explicit COLLATE notice before declaration compatibility is checked.
     *
     * @return list<array{int, \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation}> Completed notices before the refusal boundary
     */
    public function collation(Node $node, Lowering $lowering, int $end): array
    {
        if (!in_array($node->name, ['opt_collate', 'opt_collate_explicit'], true)) {
            return [];
        }
        try {
            $notice = $lowering->charsets->collation($node)?->deprecation($lowering->profile->grammar);
        } catch (\SqlSemantics\Construction\RejectedInput) {
            return [];
        }
        if ($notice === null) {
            return [];
        }
        $range = (new \SqlSemantics\Construction\SourceRanges())->span($node, true);

        return $range !== null && $range[1] <= $end ? [[$range[1], $notice]] : [];
    }

    /**
     * Returns warnings from the first completed occurrence of an attribute.
     *
     * @return list<Deprecated> The warnings supported by the input release
     */
    public function completed(Node $node, Lowering $lowering): array
    {
        if ($node->name === 'type') {
            try {
                $type = $lowering->types->type($node);
            } catch (\SqlSemantics\Construction\RejectedInput) {
                // The server warns about the written parts before refusing the size.
                return array_values(array_filter($lowering->types->deprecations($node), static fn (Deprecated $notice): bool => $notice->warnedIn($lowering->profile->grammar)));
            }
            $notices = $type instanceof Integral || $type instanceof Decimal || $type instanceof Floating ? (new TypeNotices())->numericWarnings($type) : [];
            if ($type instanceof \SqlSemantics\Platform\MySql\Statement\Type\Temporal) {
                $notices = (new TypeNotices())->temporalWarnings($type);
            }
            if ($type instanceof \SqlSemantics\Platform\MySql\Statement\Type\Character && $type->national) {
                $notices = [...$notices, ...(new TypeNotices())->charsetWarnings($type->charset), Deprecated::National];
            }

            return array_values(array_filter($notices, static fn (Deprecated $notice): bool => $notice->warnedIn($lowering->profile->grammar)));
        }
        if ($node->name === 'default_charset') {
            try {
                $name = $lowering->charsets->charset($node)->name;
            } catch (\SqlSemantics\Construction\RejectedInput) {
                return [];
            }
            $attribute = $name === null ? null : new \SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm::Named, $name);
        } elseif (in_array($node->name, ['ascii', 'unicode'], true)) {
            $attribute = $lowering->types->parts->shorthand($node);
        } elseif (in_array($node->name, ['opt_binary', 'opt_charset_with_opt_binary'], true)) {
            $first = $node->children[0] ?? null;
            if ($first instanceof Node && in_array($first->name, ['ascii', 'unicode'], true)) {
                return [];
            }
            $attribute = $lowering->types->parts->charset($node);
        } else {
            return [];
        }

        return array_values(array_filter((new TypeNotices())->charsetWarnings($attribute), static fn (Deprecated $notice): bool => $notice->warnedIn($lowering->profile->grammar)));
    }
}

<?php

declare(strict_types=1);

namespace SqlSemantics\Ast\Definition;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\TokenGroups;
use SqlSemantics\Schema\ReferentialAction;

/**
 * Reads referential actions, matching, and deferred checking from constraint syntax.
 *
 * @visibility SqlSemantics
 */
final class ReferenceReader
{
    /**
     * @return array{onDelete: ReferentialAction, onUpdate: ReferentialAction, match: string, deferrable: bool, initiallyDeferred: bool, deleteColumns: list<string>}
     */
    public static function read(Node $node, Identifiers $identifiers): array
    {
        $tokens = $node->tokens();
        $words = array_map(static fn ($token): string => strtoupper($token->text), $tokens);
        $result = ['onDelete' => ReferentialAction::NoAction, 'onUpdate' => ReferentialAction::NoAction, 'match' => 'simple', 'deferrable' => false, 'initiallyDeferred' => false, 'deleteColumns' => []];
        foreach ($words as $index => $word) {
            if ($word === 'MATCH') {
                $result['match'] = strtolower($words[$index + 1] ?? 'SIMPLE');
            } elseif ($word === 'DEFERRABLE') {
                $result['deferrable'] = ($words[$index - 1] ?? '') !== 'NOT';
            } elseif ($word === 'INITIALLY') {
                $result['initiallyDeferred'] = ($words[$index + 1] ?? '') === 'DEFERRED';
            } elseif ($word === 'ON' && in_array($words[$index + 1] ?? '', ['DELETE', 'UPDATE'], true)) {
                $action = self::action(array_slice($words, $index + 2));
                $result[$words[$index + 1] === 'DELETE' ? 'onDelete' : 'onUpdate'] = $action;
                if ($words[$index + 1] === 'DELETE' && ($words[$index + 4] ?? '') === '(') {
                    $groups = TokenGroups::parentheses(array_slice($tokens, $index + 4));
                    $result['deleteColumns'] = TokenGroups::names($groups[0] ?? [], $identifiers);
                }
            }
        }
        if ($identifiers->dialect === \SqlSemantics\Dialect::PostgreSql && $result['initiallyDeferred']) {
            $result['deferrable'] = true;
        }
        return $result;
    }

    /**

     * @param list<string> $words

     */
    public static function action(array $words): ReferentialAction
    {
        return match ($words[0] ?? '') {
            'CASCADE' => ReferentialAction::Cascade,
            'RESTRICT' => ReferentialAction::Restrict,
            'SET' => ($words[1] ?? '') === 'NULL' ? ReferentialAction::SetNull : ReferentialAction::SetDefault,
            default => ReferentialAction::NoAction,
        };
    }
}

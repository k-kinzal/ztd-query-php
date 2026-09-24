<?php

declare(strict_types=1);

namespace SqlSemantics\Ast;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Declaration\TableConstraint;
use SqlSemantics\Schema\ConstraintKind;

/**
 * Extracts integrity declarations while preserving the complete original constraint.
 *
 * @visibility SqlSemantics
 */
final class ConstraintReader
{
    /**
     * Binds the dependencies used for semantic binding.
     */
    public function __construct(public readonly Identifiers $identifiers)
    {
    }

    /**
     * Reads an integrity condition, or returns null for another attribute.
     */
    public function read(Node $node, ?string $column = null): ?TableConstraint
    {
        $tokens = $node->tokens();
        $name = null;
        if (strtoupper($tokens[0]->text ?? '') === 'CONSTRAINT') {
            $unnamed = in_array(strtoupper($tokens[1]->text ?? ''), ['PRIMARY', 'UNIQUE', 'FOREIGN', 'CHECK'], true);
            $name = isset($tokens[1]) && !$unnamed ? $this->identifiers->name($tokens[1]) : null;
            $tokens = array_slice($tokens, $unnamed ? 1 : 2);
        }
        $kind = match (strtoupper($tokens[0]->text ?? '')) {
            'PRIMARY' => ConstraintKind::PrimaryKey,
            'UNIQUE' => ConstraintKind::Unique,
            'FOREIGN', 'REFERENCES' => ConstraintKind::ForeignKey,
            'CHECK' => ConstraintKind::Check,
            default => null,
        };
        if ($kind === null) {
            return null;
        }
        $groups = TokenGroups::parentheses($tokens);
        $columns = $column === null ? $this->columns($node, $groups[0] ?? []) : [$column];
        $table = [];
        $references = [];
        if ($kind === ConstraintKind::ForeignKey) {
            [$table, $references] = $this->references($node);
        }
        $expression = $kind === ConstraintKind::Check ? (Tree::outer($node, ['a_expr', 'expr'])[0] ?? null) : null;

        return new TableConstraint($kind, $kind === ConstraintKind::Check ? [] : $columns, $node, $name, $table, $references, $expression, ...Definition\ReferenceReader::read($node, $this->identifiers));
    }

    /**
     * Reads the named key columns, skipping prefix lengths, directions and expression keys.
     * @param list<\SqlParser\Lexer\Token> $tokens Tokens of the first parenthesized group
     * @return list<string>
     */
    public function columns(Node $node, array $tokens): array
    {
        $keys = Definition\IndexKeys::read($node, $this->identifiers);
        if ($keys === []) {
            return TokenGroups::names($tokens, $this->identifiers);
        }
        return array_values(array_filter(array_map(static fn (Declaration\IndexElement $key): ?string => $key->column, $keys), is_string(...)));
    }

    /**
     * @return array{list<string>, list<string>}
     */
    public function references(Node $node): array
    {
        $remaining = [];
        $started = false;
        foreach ($node->tokens() as $token) {
            if (strtoupper($token->text) === 'REFERENCES') {
                $started = true;
            } elseif ($started) {
                $remaining[] = $token;
            }
        }
        $name = [];
        foreach ($remaining as $token) {
            if (in_array(strtoupper($token->text), ['(', 'MATCH', 'ON', 'DEFERRABLE', 'NOT', 'INITIALLY'], true)) {
                break;
            }
            if ($token->text !== '.') {
                $name[] = $this->identifiers->name($token);
            }
        }
        $referenceTokens = [];
        foreach ($remaining as $token) {
            if (in_array(strtoupper($token->text), ['MATCH', 'ON', 'DEFERRABLE', 'NOT', 'INITIALLY'], true)) {
                break;
            }
            $referenceTokens[] = $token;
        }
        $groups = TokenGroups::parentheses($referenceTokens);

        return [$name, TokenGroups::names($groups[0] ?? [], $this->identifiers)];
    }
}

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
            $name = isset($tokens[1]) ? $this->identifiers->name($tokens[1]) : null;
            $tokens = array_slice($tokens, 2);
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
        $columns = $column === null ? TokenGroups::names($groups[0] ?? [], $this->identifiers) : [$column];
        $table = [];
        $references = [];
        if ($kind === ConstraintKind::ForeignKey) {
            [$table, $references] = $this->references($node);
        }
        $expression = $kind === ConstraintKind::Check ? (Tree::outer($node, ['a_expr', 'expr'])[0] ?? null) : null;

        return new TableConstraint($kind, $kind === ConstraintKind::Check ? [] : $columns, $node, $name, $table, $references, $expression, ...Definition\ReferenceReader::read($node, $this->identifiers));
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

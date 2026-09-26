<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Ast;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Analysis\ValueReader;
use SqlSemantics\Core\Schema\ConstraintKind;
use SqlSemantics\Core\Schema\TableConstraint;

/**
 * Extracts integrity declarations while preserving the complete original constraint.
 *
 * @visibility SqlSemantics
 */
final class ConstraintReader
{
    private readonly ValueReader $values;
    /**
     * Binds the dependencies used for semantic binding.
     */
    public function __construct(public readonly Identifiers $identifiers, ?ValueReader $values = null)
    {
        $this->values = $values ?? $identifiers->dialect->platform()->values((new DialectParser($identifiers->dialect))->version());
    }

    /**
     * Reads an integrity condition, or returns null for another attribute.
     */
    public function read(Node $node, ?string $column = null): ?TableConstraint
    {
        [$name, $tokens] = TokenGroups::constraintHeader($node->tokens(), $this->identifiers);
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
        $columns = $column === null ? TokenGroups::keyNames($groups[0] ?? [], $this->identifiers) : [$column];
        $table = [];
        $references = [];
        if ($kind === ConstraintKind::ForeignKey) {
            [$table, $references] = $this->references($node);
        }
        $expression = $kind === ConstraintKind::Check ? (Tree::outer($node, $this->identifiers->dialect->platform()->syntax()->nodes('expression'))[0] ?? null) : null;

        return new TableConstraint($kind, $kind === ConstraintKind::Check ? [] : $columns, $this->values->read($node), $name, $table, $references, $expression === null ? null : $this->values->read($expression), $column !== null, $column !== null && in_array('DESC', array_map(static fn ($token): string => strtoupper($token->text), $tokens), true));
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
        $groups = TokenGroups::parentheses($remaining);

        return [$name, TokenGroups::keyNames($groups[0] ?? [], $this->identifiers)];
    }
}

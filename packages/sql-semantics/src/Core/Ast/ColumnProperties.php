<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Ast;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Analysis\ValueReader;
use SqlSemantics\Core\Schema\ColumnGeneration;
use SqlSemantics\Core\Schema\GenerationKind;
use SqlSemantics\Statement\Element;

/**
 * Reads column properties from grammar-delimited attribute nodes.
 * @visibility SqlSemantics
 */
final class ColumnProperties
{
    /**
     * Supplies identifier policy and versioned semantic lowering.
     */
    public function __construct(private readonly Identifiers $identifiers, private readonly ValueReader $values)
    {
    }

    /**
     * Preserves a computed expression or a complete identity clause.
     * @param list<Node> $attributes
     */
    public function generation(Node $column, array $attributes): ?ColumnGeneration
    {
        $candidates = [...$attributes, ...Tree::outer($column, $this->identifiers->dialect->platform()->syntax()->nodes('generationClause'))];
        foreach ($candidates as $attribute) {
            $tokens = array_map(static fn ($token): string => strtoupper($token->text), $attribute->tokens());
            if (($tokens[0] ?? '') === 'CONSTRAINT') {
                $tokens = array_slice($tokens, 2);
            }
            $direct = array_filter($attribute->children, static fn ($child): bool => $child instanceof Token && strtoupper($child->text) === 'AS');
            if ($direct === [] && !in_array($tokens[0] ?? '', ['GENERATED', 'AS'], true)) {
                continue;
            }
            $expression = Tree::outer($attribute, $this->identifiers->dialect->platform()->syntax()->nodes('expression'))[0] ?? null;
            if ($expression === null && !in_array('IDENTITY', $tokens, true)) {
                continue;
            }
            $storageNodes = [$attribute, ...Tree::outer($attribute, $this->identifiers->dialect->platform()->syntax()->nodes('generationStorage'))];
            $stored = false;
            foreach ($storageNodes as $storage) {
                foreach ($storage->children as $child) {
                    $stored = $stored || ($child instanceof Token && strtoupper($child->text) === 'STORED');
                }
            }
            $kind = $expression === null ? GenerationKind::Identity : ($stored ? GenerationKind::Stored : GenerationKind::Virtual);
            return new ColumnGeneration($kind, $this->values->read($attribute), $expression === null ? null : $this->values->read($expression));
        }
        return null;
    }

    /**
     * Preserves the declared collation with its identifier spelling.
     * @param list<Node> $attributes
     */
    public function collation(array $attributes): ?Element
    {
        foreach ($attributes as $attribute) {
            [, $tokens] = TokenGroups::constraintHeader($attribute->tokens(), $this->identifiers);
            if (strtoupper($tokens[0]->text ?? '') === 'COLLATE') {
                return $this->values->read($attribute);
            }
        }
        return null;
    }

    /**
     * Reads auto-increment markers only from column attributes.
     * @param list<Node> $attributes
     */
    public function autoIncrement(array $attributes): bool
    {
        foreach ($attributes as $attribute) {
            $clauses = [$attribute, ...Tree::outer($attribute, $this->identifiers->dialect->platform()->syntax()->nodes('autoIncrement'))];
            foreach ($clauses as $clause) {
                foreach ($clause->children as $token) {
                    if ($token instanceof Token && in_array(strtoupper($token->text), ['AUTO_INCREMENT', 'AUTOINCREMENT'], true)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
}

<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Declaration;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Identifiers;
use SqlSemantics\Core\Ast\TokenGroups;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\SchemaRules;

/**
 * Validates the engine-independent key requirement of automatic numbering.
 * @visibility SqlSemantics
 */
final class AutoIncrement
{
    /**
     * @throws SemanticException When numbering has no index or more than one column requests it
     */
    public function validate(Node $table): void
    {
        $identifiers = new Identifiers(Dialect::MySql);
        $indexed = [];
        foreach (Tree::outer($table, ['table_constraint_def', 'key_def']) as $constraint) {
            [, $tokens] = TokenGroups::constraintHeader($constraint->tokens(), $identifiers);
            if (in_array(strtoupper($tokens[0]->text ?? ''), ['PRIMARY', 'UNIQUE', 'KEY', 'INDEX'], true)) {
                array_push($indexed, ...TokenGroups::keyNames(TokenGroups::parentheses($tokens)[0] ?? [], $identifiers));
            }
        }
        $automatic = [];
        foreach ((new SchemaRules())->columnNodes($table) as [$column, $attributes]) {
            $name = $identifiers->name($column->tokens()[0]);
            $type = Tree::outer($column, ['type'])[0] ?? null;
            $serial = ($type?->tokens()[0]->name ?? '') === 'SERIAL_SYM';
            $auto = $serial;
            $key = $serial;
            foreach ($attributes as $attribute) {
                $words = array_map(static fn ($token): string => strtoupper($token->text), $attribute->tokens());
                $auto = $auto || in_array($words[0] ?? '', ['AUTO_INCREMENT', 'SERIAL'], true);
                $key = $key || in_array($words[0] ?? '', ['PRIMARY', 'KEY', 'UNIQUE', 'SERIAL'], true);
            }
            if ($auto) {
                $automatic[] = $name;
                if (!$key && !in_array(strtolower($name), array_map(strtolower(...), $indexed), true)) {
                    throw new SemanticException('unindexed-auto-increment', 'An AUTO_INCREMENT column must be indexed.', $column);
                }
            }
        }
        if (count($automatic) > 1) {
            throw new SemanticException('multiple-auto-increment', 'Only one AUTO_INCREMENT column is allowed.', $table);
        }
    }
}

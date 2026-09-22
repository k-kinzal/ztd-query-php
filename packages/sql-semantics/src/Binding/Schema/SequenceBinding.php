<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Schema;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Schema\Column\SequenceOptions;

/**
 * Classifies sequence values without coercing their arbitrary-precision integers.
 *
 * @visibility SqlSemantics
 */
final class SequenceBinding
{
    /**
     * @param list<Node> $attributes
     * @throws UnclassifiedSql
     */
    public static function read(array $attributes, Scope $scope): SequenceOptions
    {
        $values = [];
        $cycle = null;
        foreach ($attributes as $attribute) {
            foreach (Tree::outer($attribute, ['SeqOptElem']) as $option) {
                $words = array_map(static fn ($token): string => strtoupper($token->text), $option->tokens());
                if (in_array('CYCLE', $words, true)) {
                    $cycle = $words[0] !== 'NO';
                    continue;
                }
                $name = match ($words[0] ?? '') {
                    'START' => 'start', 'INCREMENT' => 'increment', 'MINVALUE' => 'minimum',
                    'MAXVALUE' => 'maximum', 'CACHE' => 'cache',
                    default => throw new UnclassifiedSql('Unclassified sequence option: ' . Tree::text($option)),
                };
                $node = Tree::outer($option, ['NumericOnly'])[0] ?? new Node('sequence_integer', 0, array_slice($option->children, -1));
                $value = (new ExpressionBinder())->bind($node, $scope);
                if (!$value instanceof Literal) {
                    throw new UnclassifiedSql('A sequence option must be an integer literal.');
                }
                $values[$name] = $value;
            }
        }
        return new SequenceOptions($values['start'] ?? null, $values['increment'] ?? null, $values['minimum'] ?? null, $values['maximum'] ?? null, $values['cache'] ?? null, $cycle);
    }
}

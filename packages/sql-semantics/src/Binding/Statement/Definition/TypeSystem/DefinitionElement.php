<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\TypeSystem;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * One name = argument element of a definition list; the argument is null when the element names only the attribute or, in ALTER, spells NONE.
 * @visibility SqlSemantics
 */
final class DefinitionElement
{
    /**
     * Binds the dependencies used for semantic binding.
     */
    public function __construct(public readonly string $name, public readonly ?Node $argument, public readonly Node $source)
    {
    }

    /**
     * Reads the elements of a definition, an ALTER attribute list, or an old-style aggregate definition in order.
     * @return list<DefinitionElement>
     * @throws UnclassifiedSql
     */
    public static function list(Node $source, QueryContext $context): array
    {
        return array_map(static fn (Node $element): DefinitionElement => new self(
            $context->tables->identifiers->name($element->tokens()[0] ?? throw new UnclassifiedSql('A definition element requires its name.')),
            Tree::child($element, ['def_arg', 'operator_def_arg']),
            $element,
        ), Tree::outer($source, ['def_elem', 'operator_def_elem', 'old_aggr_elem']));
    }

    /**
     * Keys elements by name; names outside the allowed set are rejected, and a repeated name is rejected unless the last one counts.
     * @param list<DefinitionElement> $elements
     * @param list<string> $allowed
     * @return array<string, DefinitionElement>
     * @throws InvalidSql
     */
    public static function named(array $elements, array $allowed, bool $lastCounts): array
    {
        $named = [];
        foreach ($elements as $element) {
            if (!in_array($element->name, $allowed, true) || (!$lastCounts && isset($named[$element->name]))) {
                throw new InvalidSql(InputViolation::DefinitionAttribute, $element->source);
            }
            $named[$element->name] = $element;
        }
        return $named;
    }
}

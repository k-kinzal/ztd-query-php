<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Foreign\ForeignOption;
use SqlSemantics\Model\Definition\Foreign\ForeignRelation;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Reads remote relation selectors and literal-valued foreign-data wrapper options.
 * @visibility SqlSemantics
 */
final class ForeignOperands
{
    /**
     * Keeps qualification and descendant scope for the foreign-data wrapper.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function relation(Node $source, Identifiers $identifiers): ForeignRelation
    {
        $name = Tree::outer($source, ['qualified_name'])[0] ?? throw new UnclassifiedSql('A foreign relation selector requires its name.');
        foreach (Tree::outer($name, ['indirection_el']) as $part) {
            if (Tree::child($part, ['attr_name']) === null) {
                throw new InvalidSql(InputViolation::RelationName, $name);
            }
        }
        $parts = $identifiers->parts($name);
        if (count($parts) > 3 || in_array('', $parts, true)) {
            throw new InvalidSql(InputViolation::RelationName, $name);
        }
        return new ForeignRelation(new QualifiedName($parts), strtoupper($source->tokens()[0]->text) !== 'ONLY');
    }

    /**
     * Requires a text literal rather than accepting arbitrary option expressions.
     * @throws UnclassifiedSql
     */
    public static function option(Node $source, Identifiers $identifiers): ForeignOption
    {
        $name = Tree::child($source, ['generic_option_name']) ?? throw new UnclassifiedSql('A foreign option requires its name.');
        $argument = Tree::child($source, ['generic_option_arg']) ?? throw new UnclassifiedSql('A foreign option requires its text operand.');
        $value = (new LiteralBinder(Dialect::PostgreSql))->bind($argument->tokens()[0]);
        if (!$value instanceof Literal) {
            throw new UnclassifiedSql('A foreign option requires a text literal.');
        }
        return new ForeignOption($identifiers->name($name->tokens()[0]), $value);
    }
}

<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Utility;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Reads a PostgreSQL qualified_name as the server's name reader does: at most catalog, schema and object.
 * @visibility SqlSemantics
 */
final class QualifiedNames
{
    /**
     * Subscripts, wildcards and more than three components are rejected as improper qualified names.
     * @param InputViolation $violation Diagnosis naming the kind of object the name refers to
     * @throws InvalidSql
     */
    public static function read(Node $name, Identifiers $identifiers, InputViolation $violation = InputViolation::RelationName): QualifiedName
    {
        foreach (Tree::outer($name, ['indirection_el']) as $part) {
            if (Tree::child($part, ['attr_name']) === null) {
                throw new InvalidSql($violation, $name);
            }
        }
        $parts = $identifiers->parts($name);
        if ($parts === [] || count($parts) > 3 || in_array('', $parts, true)) {
            throw new InvalidSql($violation, $name);
        }
        return new QualifiedName($parts);
    }
}

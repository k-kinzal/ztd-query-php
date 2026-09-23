<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Spatial\Organization;
use SqlSemantics\Model\Definition\Spatial\SpatialDefinition;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Reads each unique spatial metadata attribute into its own typed property.
 * @visibility SqlSemantics
 */
final class SpatialMetadata
{
    /**
     * Requires NAME and DEFINITION and preserves the paired ORGANIZATION operands.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function read(Node $source): SpatialDefinition
    {
        $name = null;
        $definition = null;
        $organization = null;
        $description = null;
        $seen = [];
        $node = Tree::child($source, ['srs_attributes']);
        while ($node !== null) {
            $keyword = array_values(array_filter($node->children, static fn ($child): bool => $child instanceof \SqlParser\Lexer\Token))[0] ?? throw new UnclassifiedSql('A spatial attribute requires its keyword.');
            $kind = strtoupper($keyword->text);
            if (isset($seen[$kind])) {
                throw new InvalidSql(InputViolation::SpatialAttribute, $node);
            }
            $seen[$kind] = true;
            $literal = Tree::child($node, ['TEXT_STRING_sys_nonewline']) ?? throw new UnclassifiedSql('A spatial attribute requires its text literal.');
            $value = (new LiteralBinder(Dialect::MySql))->bind($literal->tokens()[0]);
            if (!$value instanceof Literal) {
                throw new UnclassifiedSql('A spatial attribute requires a text literal.');
            }
            match ($kind) {
                'NAME' => $name = $value,
                'DEFINITION' => $definition = $value,
                'DESCRIPTION' => $description = $value,
                'ORGANIZATION' => $organization = new Organization($value, SpatialDefinitions::identifier(Tree::child($node, ['real_ulonglong_num']) ?? throw new UnclassifiedSql('An organization requires its coordinate-system identifier.'))),
                default => throw new UnclassifiedSql('Unknown spatial-definition attribute.'),
            };
            $node = Tree::child($node, ['srs_attributes']);
        }
        if ($name === null || $definition === null) {
            throw new InvalidSql(InputViolation::SpatialAttribute, $source);
        }
        return new SpatialDefinition($name, $definition, $organization, $description);
    }
}

<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\TypeSystem;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\TypeSystem\Definition\BaseTypeAttribute;
use SqlSemantics\Model\Definition\TypeSystem\Definition\RangeAttribute;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Type as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds base type and range type definitions and ALTER TYPE ... SET.
 * @visibility SqlSemantics
 */
final class TypeOptions
{
    /**
     * Unrecognized attributes are ignored as the server ignores them; a repeated attribute is rejected.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function base(Origin $origin, QualifiedName $name, Node $source, QueryContext $context): Statement\CreateBaseTypeStatement
    {
        $options = [];
        foreach (DefinitionElement::list(Tree::child($source, ['definition']) ?? $source, $context) as $element) {
            $attribute = self::attribute($element->name === 'analyse' ? 'analyze' : $element->name);
            if ($attribute === null) {
                continue;
            }
            if (isset($options[$attribute->value])) {
                throw new InvalidSql(InputViolation::DefinitionAttribute, $element->source);
            }
            $options[$attribute->value] = DefinitionArguments::present($element, $attribute, $context);
        }
        try {
            return new Statement\CreateBaseTypeStatement($origin, $name, Collections::nonEmpty(array_values($options)));
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::DefinitionRequirement, $source, $error);
        }
    }

    /**
     * Every range attribute is recognized, given once, and has an argument.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function range(Origin $origin, QualifiedName $name, Node $source, QueryContext $context): Statement\CreateRangeTypeStatement
    {
        $options = [];
        foreach (DefinitionElement::list(Tree::child($source, ['definition']) ?? $source, $context) as $element) {
            $attribute = $element->name === strtolower($element->name) ? RangeAttribute::tryFrom(strtoupper($element->name)) : null;
            if ($attribute === null || isset($options[$attribute->value])) {
                throw new InvalidSql(InputViolation::DefinitionAttribute, $element->source);
            }
            $options[$attribute->value] = DefinitionArguments::present($element, $attribute, $context);
        }
        try {
            return new Statement\CreateRangeTypeStatement($origin, $name, Collections::nonEmpty(array_values($options)));
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::DefinitionRequirement, $source, $error);
        }
    }

    /**
     * ALTER TYPE ... SET changes only the optional support functions and the storage; a repeated attribute keeps its last argument.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function alter(Origin $origin, Node $source, QueryContext $context): Statement\AlterTypeOptionsStatement
    {
        $options = [];
        foreach (DefinitionElement::list(Tree::child($source, ['operator_def_list']) ?? $source, $context) as $element) {
            $attribute = self::attribute($element->name);
            if ($attribute === null || !$attribute->alterable()) {
                throw new InvalidSql(InputViolation::DefinitionAttribute, $element->source);
            }
            $option = DefinitionArguments::option($element, $attribute, $context);
            if ($option->value === null && $attribute === BaseTypeAttribute::Storage) {
                throw new InvalidSql(InputViolation::DefinitionArgument, $element->source);
            }
            unset($options[$attribute->value]);
            $options[$attribute->value] = $option;
        }
        return new Statement\AlterTypeOptionsStatement($origin, TypeDefinitions::name($source, $context), Collections::nonEmpty(array_values($options)));
    }

    /**
     * Resolves a lowercase attribute name; quoted names in other cases match nothing.
     */
    public static function attribute(string $name): ?BaseTypeAttribute
    {
        return $name === strtolower($name) ? BaseTypeAttribute::tryFrom(strtoupper($name)) : null;
    }
}

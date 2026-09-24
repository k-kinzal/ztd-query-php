<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\TypeSystem;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Definition\Catalog\ObjectAddresses;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Catalog\OperatorIdentity;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionOption;
use SqlSemantics\Model\Definition\TypeSystem\Definition\OperatorAttribute;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Operator as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds CREATE OPERATOR, ALTER OPERATOR, and DROP OPERATOR.
 * @visibility SqlSemantics
 */
final class Operators
{
    /**
     * Attribute spellings the server reads as another attribute; the obsolete sort operators imply MERGES.
     */
    public const ALIASES = ['procedure' => 'FUNCTION', 'sort1' => 'MERGES', 'sort2' => 'MERGES', 'ltcmp' => 'MERGES', 'gtcmp' => 'MERGES'];

    /**
     * Unrecognized attributes are ignored as the server ignores them; a repeated attribute keeps its last argument.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function create(Origin $origin, Node $source, QueryContext $context): Statement\CreateOperatorStatement
    {
        $name = ObjectAddresses::name(Tree::child($source, ['any_operator']) ?? throw new UnclassifiedSql('An operator requires its name.'), $context, 2);
        $options = [];
        foreach (DefinitionElement::list(Tree::child($source, ['definition']) ?? $source, $context) as $element) {
            $attribute = self::attribute($element->name);
            if ($attribute === null) {
                continue;
            }
            $option = isset(self::ALIASES[$element->name]) && $attribute === OperatorAttribute::Merges ? new DefinitionOption($attribute, true) : self::option($element, $attribute, $context);
            if ($option->value === null) {
                throw new InvalidSql(InputViolation::DefinitionArgument, $element->source);
            }
            unset($options[$attribute->value]);
            $options[$attribute->value] = $option;
        }
        try {
            return new Statement\CreateOperatorStatement($origin, $name, Collections::nonEmpty(array_values($options)));
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::DefinitionRequirement, $source, $error);
        }
    }

    /**
     * ALTER OPERATOR ... SET accepts only the attributes that can change; NONE removes an estimator or partner.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function alter(Origin $origin, Node $source, QueryContext $context): Statement\AlterOperatorStatement
    {
        $operator = ObjectAddresses::operator(Tree::child($source, ['operator_with_argtypes']) ?? throw new UnclassifiedSql('ALTER OPERATOR requires its operator.'), $context);
        $options = [];
        foreach (DefinitionElement::list(Tree::child($source, ['operator_def_list']) ?? $source, $context) as $element) {
            $attribute = self::attribute($element->name);
            if ($attribute === null || !$attribute->alterable() || isset(self::ALIASES[$element->name])) {
                throw new InvalidSql(InputViolation::DefinitionAttribute, $element->source);
            }
            unset($options[$attribute->value]);
            $options[$attribute->value] = self::option($element, $attribute, $context);
        }
        return new Statement\AlterOperatorStatement($origin, $operator, Collections::nonEmpty(array_values($options)));
    }

    /**
     * DROP OPERATOR lists operators with their operand types.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function drop(Origin $origin, Node $source, QueryContext $context): Statement\DropOperatorsStatement
    {
        $operators = array_map(static fn (Node $operator): OperatorIdentity => ObjectAddresses::operator($operator, $context), Tree::outer($source, ['operator_with_argtypes']));
        return new Statement\DropOperatorsStatement($origin, Collections::nonEmpty($operators), in_array('EXISTS', DefinitionWords::of($source), true), Domains::behavior($source));
    }

    /**
     * Resolves a lowercase attribute name or alias; quoted names in other cases match nothing.
     */
    public static function attribute(string $name): ?OperatorAttribute
    {
        return $name === strtolower($name) ? OperatorAttribute::tryFrom(self::ALIASES[$name] ?? strtoupper($name)) : null;
    }

    /**
     * Operand types cannot be SETOF.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function option(DefinitionElement $element, OperatorAttribute $attribute, QueryContext $context): DefinitionOption
    {
        $declaration = $element->argument === null ? null : Tree::outer($element->argument, ['Typename'])[0] ?? null;
        if (in_array($attribute, [OperatorAttribute::LeftArg, OperatorAttribute::RightArg], true) && $declaration !== null && TypeDefinitions::setOf($declaration)) {
            throw new InvalidSql(InputViolation::SetOfDeclaration, $declaration);
        }
        return DefinitionArguments::option($element, $attribute, $context);
    }
}

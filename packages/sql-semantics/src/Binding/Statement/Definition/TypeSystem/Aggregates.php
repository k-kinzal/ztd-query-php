<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\TypeSystem;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Routine\Targets;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine;
use SqlSemantics\Model\Definition\TypeSystem\Definition\AggregateAttribute;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Aggregate\CreateAggregateStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds CREATE AGGREGATE in its signature syntax and in its old BASETYPE syntax.
 * @visibility SqlSemantics
 */
final class Aggregates
{
    /**
     * Attribute spellings of the old syntax that the server reads as another attribute.
     */
    public const ALIASES = ['sfunc1' => 'SFUNC', 'stype1' => 'STYPE', 'initcond1' => 'INITCOND'];

    /**
     * Unrecognized attributes are ignored and a repeated attribute keeps its last argument, as the server does.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function create(Origin $origin, Node $source, QueryContext $context): CreateAggregateStatement
    {
        $legacy = Tree::child($source, ['old_aggr_definition']);
        $baseType = null;
        $options = [];
        foreach (DefinitionElement::list($legacy ?? Tree::child($source, ['definition']) ?? $source, $context) as $element) {
            if ($element->name === 'basetype') {
                $baseType = $legacy === null ? throw new InvalidSql(InputViolation::DefinitionAttribute, $element->source) : $element;
                continue;
            }
            $attribute = $element->name === strtolower($element->name) ? AggregateAttribute::tryFrom(self::ALIASES[$element->name] ?? strtoupper($element->name)) : null;
            if ($attribute === null) {
                continue;
            }
            unset($options[$attribute->value]);
            $options[$attribute->value] = DefinitionArguments::present($element, $attribute, $context);
        }
        $aggregate = $legacy === null ? Targets::aggregate($source, $context) : self::legacy($source, $baseType, $context);
        $signature = $legacy === null ? Tree::child($source, ['aggr_args']) : null;
        foreach ($signature === null ? [] : Tree::outer($signature, ['func_type']) as $type) {
            if (TypeDefinitions::setOf($type)) {
                throw new InvalidSql(InputViolation::SetOfDeclaration, $type);
            }
        }
        try {
            return new CreateAggregateStatement($origin, $aggregate, Collections::nonEmpty(array_values($options)), Tree::child($source, ['opt_or_replace']) !== null);
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::DefinitionRequirement, $source, $error);
        }
    }

    /**
     * BASETYPE = ANY declares a zero-argument aggregate; any other base type one implicit argument.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function legacy(Node $source, ?DefinitionElement $baseType, QueryContext $context): Routine\ZeroArgumentAggregate|Routine\OrdinaryAggregate
    {
        $argument = ($baseType === null ? null : $baseType->argument) ?? throw new InvalidSql(InputViolation::DefinitionRequirement, $source);
        $name = Targets::name(Tree::child($source, ['func_name']) ?? throw new UnclassifiedSql('An aggregate requires its name.'), $context);
        $words = DefinitionArguments::words($argument, $context);
        if ($words !== null && strtolower(implode('.', $words)) === 'any') {
            return new Routine\ZeroArgumentAggregate($name);
        }
        return new Routine\OrdinaryAggregate($name, [new Routine\AggregateParameter(DefinitionArguments::type($argument, $context))]);
    }
}

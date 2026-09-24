<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Relation;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\ConstraintGroups;
use SqlSemantics\Ast\ConstraintReader;
use SqlSemantics\Ast\Definition\OptionReader;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Schema\ConstraintBinder;
use SqlSemantics\Binding\Schema\DefinitionBinder;
use SqlSemantics\Binding\Schema\OptionBinding;
use SqlSemantics\Binding\Schema\SequenceBinding;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Relation\Foreign\PartitionColumn;
use SqlSemantics\Schema\Column;
use SqlSemantics\Type\Nullability;

/**
 * Reads the column overrides of a partition, which name an inherited column without restating its type.
 * @visibility SqlSemantics
 */
final class PartitionColumns
{
    /**
     * Binds nullability, generation, collation, and column constraints of one override.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function read(Node $element, Scope $scope): PartitionColumn
    {
        $identifiers = $scope->identifiers;
        $name = $identifiers->name((Tree::child($element, ['ColId']) ?? throw new UnclassifiedSql('A column override requires its column.'))->tokens()[0]);
        $attributes = (new ConstraintGroups())->read(Tree::outer($element, ['ColConstraint']));
        $options = OptionReader::column($element, $attributes, $identifiers);
        $nullability = Nullability::MaybeNull;
        $generation = new Column\SuppliedColumn();
        $constraints = [];
        foreach ($attributes as $attribute) {
            $constraint = (new ConstraintReader($identifiers))->read($attribute, $name);
            if ($constraint !== null) {
                $constraints[] = ConstraintBinder::bind($constraint, $scope);
                continue;
            }
            $generation = self::generation($attribute, $attributes, $options, $scope) ?? $generation;
            $nullability = str_starts_with(self::words($attribute), 'NOT NULL') || $generation instanceof Column\IdentityColumn ? Nullability::NotNull : $nullability;
        }
        return new PartitionColumn($name, $nullability, $generation, OptionBinding::qualified($options, 'collation'), $constraints);
    }

    /**
     * Returns the generation an attribute declares, or null when the attribute declares none.
     * @param list<Node> $attributes
     * @param array<string, string|bool|list<string>> $options
     * @throws UnclassifiedSql
     */
    public static function generation(Node $attribute, array $attributes, array $options, Scope $scope): ?Column\Generation
    {
        $words = self::words($attribute);
        if (str_starts_with($words, 'DEFAULT ')) {
            return new Column\SuppliedColumn((new DefinitionBinder())->expression($attribute, $scope));
        }
        if (str_contains($words, 'IDENTITY')) {
            return new Column\IdentityColumn(Column\IdentityMode::from(OptionBinding::string($options, 'identity') ?? 'by-default'), SequenceBinding::read($attributes, $scope));
        }
        if (str_starts_with($words, 'GENERATED')) {
            $expression = Tree::outer($attribute, ['a_expr'])[0] ?? throw new UnclassifiedSql('A generated column requires its expression.');
            return new Column\ComputedColumn((new DefinitionBinder())->expression($expression, $scope), Column\GeneratedStorage::Stored);
        }
        return null;
    }

    /**
     * Spells an attribute in uppercase words without its constraint name.
     */
    public static function words(Node $attribute): string
    {
        $tokens = $attribute->tokens();
        if (strtoupper($tokens[0]->text) === 'CONSTRAINT') {
            $tokens = array_slice($tokens, 2);
        }
        return strtoupper(implode(' ', array_map(static fn ($token): string => $token->text, $tokens)));
    }
}

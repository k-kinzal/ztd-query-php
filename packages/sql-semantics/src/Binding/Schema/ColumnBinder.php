<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Schema;

use SqlSemantics\Ast\Declaration\ColumnDefinition as ParsedColumn;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Schema\Column;
use SqlSemantics\Schema\ColumnDefinition;

/**
 * Resolves the value source and attributes of a parsed column declaration.
 *
 * @visibility SqlSemantics
 */
final class ColumnBinder
{
    /**
     * Binds the declaration against its complete column namespace.
     * @throws \SqlSemantics\InvalidSql
     */
    public static function bind(ParsedColumn $column, Scope $scope): ColumnDefinition
    {
        $generation = self::generation($column, $scope);
        foreach ($generation->expressions() as $expression) {
            (new \SqlSemantics\Binding\Write\AssignmentRules())->checkType($column->type, $expression, $scope);
        }
        $options = $column->options;
        $ownsEncoding = $column->type->identity instanceof \SqlSemantics\Type\Identity\StringStorage || $column->type->identity instanceof \SqlSemantics\Type\Identity\Enumeration || $column->type->identity instanceof \SqlSemantics\Type\Identity\LabelSet;
        $postgreSql = $scope->identifiers->dialect === \SqlSemantics\Dialect::PostgreSql;
        $attributes = new Column\Attributes(
            collation: OptionBinding::qualified($options, 'collation'),
            characterSet: $ownsEncoding ? null : OptionBinding::string($options, 'character_set'),
            comment: OptionBinding::string($options, 'comment'),
            visible: isset($options['invisible']) ? false : (isset($options['visible']) ? true : null),
            storage: ($value = OptionBinding::string($options, 'storage')) === null || $postgreSql ? null : Column\Storage::from(strtolower($value)),
            format: ($value = OptionBinding::string($options, 'column_format')) === null ? null : Column\Format::from(strtolower($value)),
            compression: OptionBinding::string($options, 'compression'),
            engineAttribute: OptionBinding::string($options, 'engine_attribute'),
            secondaryEngineAttribute: OptionBinding::string($options, 'secondary_engine_attribute'),
            spatialReferenceId: OptionBinding::integer($options, 'srid'),
            zeroFill: isset($options['zerofill']),
            binary: !$ownsEncoding && isset($options['binary']),
            storageStrategy: ($value = OptionBinding::string($options, 'storage')) === null || !$postgreSql ? null : (\SqlSemantics\Model\Definition\Relation\Column\ColumnStorageMode::tryFrom(strtoupper($value)) ?? throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::ColumnStorage, $column->source)),
            excludedFromSecondaryEngine: isset($options['not_secondary']),
        );
        OptionBinding::classified($options, ['collation', 'character_set', 'comment', 'invisible', 'visible', 'storage', 'column_format', 'compression', 'engine_attribute', 'secondary_engine_attribute', 'srid', 'zerofill', 'binary', 'signed', 'unsigned', 'auto_increment', 'serial_default', 'serial', 'identity', 'start', 'increment', 'minvalue', 'maxvalue', 'cache', 'cycle', 'no', 'as', 'sequence', 'restart', 'owned', 'logged', 'unlogged', 'generated_storage', 'on_update', 'not_secondary']);
        $nullDeclared = $column->nullability !== \SqlSemantics\Type\Nullability::NotNull && array_filter($column->attributes, static fn (\SqlParser\Parser\Node $attribute): bool => \SqlSemantics\Ast\ColumnReader::attributeWords($attribute) === ['NULL']) !== [];
        return new ColumnDefinition($column->name, $column->type, $column->nullability, $column->source, $generation, $attributes, self::nullConflict($column), $nullDeclared);
    }

    /**
     * Reads the SQLite ON CONFLICT resolution of a column's NOT NULL constraint; the last NOT NULL written decides, as in SQLite.
     */
    public static function nullConflict(ParsedColumn $column): \SqlSemantics\Model\Write\Policy\ConstraintResponse
    {
        $resolution = \SqlSemantics\Model\Write\Policy\ConstraintResponse::Default;
        foreach ($column->attributes as $attribute) {
            $tokens = $attribute->name === 'ccons' ? $attribute->tokens() : [];
            if (strtoupper(($tokens[0]->text ?? '') . ' ' . ($tokens[1]->text ?? '')) === 'NOT NULL') {
                $resolution = ConstraintBinder::resolution($attribute);
            }
        }
        return $column->nullability === \SqlSemantics\Type\Nullability::NotNull ? $resolution : \SqlSemantics\Model\Write\Policy\ConstraintResponse::Default;
    }

    /**
     * Selects one value-generation form without evaluating the expression; SQLite takes AUTOINCREMENT only on an
     * INTEGER PRIMARY KEY that aliases the rowid, which a column-level DESC key does not.
     * @throws UnclassifiedSql
     * @throws \SqlSemantics\InvalidSql
     */
    public static function generation(ParsedColumn $column, Scope $scope): Column\Generation
    {
        if (isset($column->options['auto_increment'])) {
            if ($scope->identifiers->dialect === \SqlSemantics\Dialect::Sqlite && ($column->type->name !== 'integer' || array_filter($column->attributes, static fn (\SqlParser\Parser\Node $attribute): bool => strtoupper(trim(Tree::text(Tree::outer($attribute, ['sortorder'])[0] ?? new \SqlParser\Parser\Node('sortorder', 0, [])))) === 'DESC') !== [])) {
                throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::AutoIncrementKey, $column->source);
            }
            return new Column\AutoIncrementColumn(isset($column->options['serial_default']));
        }
        if (isset($column->options['serial'])) {
            if ($column->defaultExpression !== null || isset($column->options['identity']) || $column->generatedExpression !== null) {
                throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::SerialDefault, $column->source);
            }
            return new Column\SerialColumn();
        }
        if (isset($column->options['identity'])) {
            return new Column\IdentityColumn(Column\IdentityMode::from(OptionBinding::string($column->options, 'identity') ?? 'by-default'), SequenceBinding::read($column->attributes, $scope));
        }
        if ($column->generatedExpression !== null) {
            return new Column\ComputedColumn((new DefinitionBinder())->expression($column->generatedExpression, $scope), Column\GeneratedStorage::from(OptionBinding::string($column->options, 'generated_storage') ?? ($scope->identifiers->dialect === \SqlSemantics\Dialect::PostgreSql ? 'stored' : 'virtual')));
        }
        $onUpdate = null;
        foreach ($column->attributes as $attribute) {
            if (str_starts_with(strtoupper(Tree::text($attribute)), 'ON UPDATE ')) {
                $value = Tree::outer($attribute, ['now', 'expr', 'a_expr'])[0] ?? null;
                if ($value === null) {
                    throw new UnclassifiedSql('An ON UPDATE attribute requires a classified value expression.');
                }
                $onUpdate = (new ExpressionBinder())->bind($value, $scope);
            }
        }
        return new Column\SuppliedColumn($column->defaultExpression === null ? null : (new DefinitionBinder())->expression($column->defaultExpression, $scope), $onUpdate);
    }
}

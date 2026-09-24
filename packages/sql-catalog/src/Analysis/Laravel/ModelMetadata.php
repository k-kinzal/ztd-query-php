<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis\Laravel;

use PhpParser\ConstExprEvaluationException;
use PhpParser\ConstExprEvaluator;
use PhpParser\Node\Expr;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Php\ProgramIndex;

/**
 * Reads source-declared model metadata without loading model or framework code.
 *
 * @visibility root
 */
final class ModelMetadata
{
    /**
     * The framework contract identified by model.
     */
    public const MODEL = 'Illuminate\Database\Eloquent\Model';

    /**
     * The framework contract identified by builder.
     */
    public const BUILDER = 'Illuminate\Database\Eloquent\Builder';

    /**
     * The framework contract identified by soft_deletes.
     */
    public const SOFT_DELETES = 'Illuminate\Database\Eloquent\SoftDeletes';

    /**
     * Configures the source metadata and SQL grammar used by this model.
     */
    public function __construct(private readonly ProgramIndex $index)
    {
    }

    /**
     * The model's explicit defaults and known framework effects.
     */
    public function state(string $className, ?string $dialect): QueryState
    {
        $table = $this->property($className, 'table');
        $table ??= $this->conventionalTable($className);
        $key = $this->property($className, 'primaryKey') ?? 'id';
        $state = new QueryState([
            'table' => is_string($table) ? Domain::literal($table) : Domain::unknown('Eloquent table name'),
            'key' => is_string($table) && is_string($key) ? Domain::literal($table . '.' . $key) : Domain::unknown('Eloquent primary key'),
            'model' => Domain::literal($className),
            'dialect' => Domain::literal($dialect),
            'timestamps' => Domain::literal($this->property($className, 'timestamps') !== false),
        ]);
        if ($this->index->isInstanceOf($className, self::SOFT_DELETES)) {
            $deleted = $this->constant($className, 'DELETED_AT') ?? 'deleted_at';
            $state = $state->with('softDeletes', Domain::literal(true))->with('deletedColumn', is_string($deleted) ? Domain::literal($deleted) : Domain::unknown('Eloquent deleted column'));
        }

        return $this->guard($className, $state);
    }

    /**
     * Model hooks and custom builders cannot be silently treated as standard behavior.
     */
    public function guard(string $className, QueryState $state): QueryState
    {
        foreach ($this->index->lineage($className) as $shape) {
            if ($shape->hasAttributes) {
                $state = $state->reject('Eloquent attributes may customize the query');
            }
            $state = $this->guardDefaults($shape, $state);
            if (str_starts_with($shape->name, 'Illuminate\\')) {
                continue;
            }
            foreach ($shape->traits as $trait) {
                if (!in_array($trait, [self::SOFT_DELETES, 'Illuminate\\Database\\Eloquent\\Factories\\HasFactory'], true)) {
                    $state = $state->reject('Unmodelled Eloquent trait: ' . $trait);
                }
            }
            foreach (['boot', 'booted', 'gettable', 'getconnection', 'newbasequerybuilder', 'neweloquentbuilder', 'newquery', 'newmodelquery', 'query', 'getkeyname', '__construct', '__call', '__callstatic', 'get', 'first', 'find', 'select', 'where', 'insert', 'update', 'delete', 'pluck', 'count', 'getqualifiedkeyname', 'getdeletedatcolumn', 'getqualifieddeletedatcolumn', 'orderby', 'orderbydesc', 'orderbyraw', 'limit', 'offset', 'take', 'skip', 'groupby', 'havingraw', 'distinct', 'selectraw', 'addselect', 'wherein', 'wherenotin', 'wherenull', 'wherenotnull', 'wherebetween', 'wherenotbetween', 'wherecolumn', 'whereraw', 'orwhere', 'join', 'leftjoin', 'rightjoin'] as $method) {
                if (isset($shape->methods[$method])) {
                    $state = $state->reject('Unmodelled Eloquent override or boot hook: ' . $shape->name . '::' . $method);
                }
            }
            foreach (['table', 'primaryKey', 'connection', 'timestamps', 'with', 'withCount'] as $property) {
                if (isset($shape->assignedProperties[$property])) {
                    $state = $state->reject('Mutable Eloquent metadata: ' . $property);
                }
            }
        }

        return $state;
    }

    /**
     * Rejects unresolved defaults and implicit additional queries.
     */
    public function guardDefaults(\SqlCatalog\Php\ClassShape $shape, QueryState $state): QueryState
    {
        foreach (['table', 'primaryKey', 'connection', 'timestamps', 'with', 'withCount'] as $property) {
            $expression = $shape->propertyDefaults[$property] ?? null;
            if ($expression === null) {
                continue;
            }
            try {
                $value = (new ConstExprEvaluator())->evaluateDirectly($expression);
                if (in_array($property, ['with', 'withCount'], true) && $value !== []) {
                    $state = $state->reject('Implicit Eloquent eager loads are not modelled');
                } elseif (!in_array($property, ['with', 'withCount'], true) && $value !== null && !is_scalar($value)) {
                    $state = $state->reject('Invalid Eloquent metadata: ' . $property);
                }
            } catch (ConstExprEvaluationException) {
                $state = $state->reject('Unresolved Eloquent metadata: ' . $property);
            }
        }
        if (isset($shape->constants['DELETED_AT']) && $this->scalar($shape->constants['DELETED_AT']) === null) {
            $state = $state->reject('Unresolved Eloquent deleted column');
        }

        return $state;
    }

    /**
     * Framework model ancestors identifiable even when vendor files are excluded.
     */
    public function recognizes(?string $className): bool
    {
        foreach ([self::MODEL, 'Illuminate\\Foundation\\Auth\\User', 'Illuminate\\Database\\Eloquent\\Relations\\Pivot', 'Illuminate\\Database\\Eloquent\\Relations\\MorphPivot'] as $base) {
            if ($this->index->isInstanceOf($className, $base)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A scalar property default, searching the model before its ancestors.
     */
    public function property(string $className, string $property): string|int|float|bool|null
    {
        foreach ($this->index->lineage($className) as $shape) {
            if (isset($shape->propertyDefaults[$property])) {
                return $this->scalar($shape->propertyDefaults[$property]);
            }
        }

        return null;
    }

    /**
     * A scalar model constant, such as DELETED_AT.
     */
    public function constant(string $className, string $constant): string|int|float|bool|null
    {
        $expression = $this->index->findClassConstant($className, $constant);

        return $expression === null ? null : $this->scalar($expression);
    }

    /**
     * A literal default; arbitrary expressions are left unresolved.
     */
    public function scalar(Expr $expression): string|int|float|bool|null
    {
        try {
            $value = (new ConstExprEvaluator())->evaluateDirectly($expression);

            return is_scalar($value) ? $value : null;
        } catch (ConstExprEvaluationException) {
            return null;
        }
    }

    /**
     * Common unambiguous English conventions; other inflections require an explicit table.
     */
    public function conventionalTable(string $className): ?string
    {
        $parts = explode('\\', $className);
        $name = end($parts);
        $snake = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
        $words = explode('_', $snake);
        $word = end($words);
        $known = ['user', 'post', 'comment', 'account', 'order', 'item', 'product', 'role', 'permission', 'team', 'member', 'model', 'record', 'event', 'tag', 'customer', 'invoice', 'task', 'project'];

        return in_array($word, $known, true) ? $snake . 's' : null;
    }
}

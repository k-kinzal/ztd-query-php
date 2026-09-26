<?php

declare(strict_types=1);

namespace SqlCatalog\Extension\Laravel;

use Override;
use SqlCatalog\Core\Extension\Model;
use SqlCatalog\Core\Extension\SinkCallKind;
use SqlCatalog\Core\Extension\SinkRole;
use SqlCatalog\Core\Extension\SinkSpec;
use SqlCatalog\Core\Sql\StatementKind;
use SqlCatalog\Extension\Laravel;

/**
 * Laravel's raw SQL calls and Query Builder/Eloquent execution boundaries.
 *
 * @visibility root
 */
final class LaravelExtension implements Model\ModelProviderInterface
{
    /**
     * Supplies SQL policies independently of the framework model.
     */
    public function __construct(private readonly \SqlCatalog\Core\Sql\Dialects $dialects = new \SqlCatalog\Core\Sql\Dialects())
    {
    }

    private const FACADE = 'Illuminate\Support\Facades\DB';

    private const CONNECTION = 'Illuminate\Database\Connection';

    /**
     * The name the command line selects this extension by.
     */
    #[Override]
    public function name(): string
    {
        return 'laravel';
    }

    /**
     * What the extension covers.
     */
    #[Override]
    public function description(): string
    {
        return 'Laravel raw SQL, Query Builder and Eloquent';
    }

    /**
     * The Laravel calls that carry a statement.
     *
     * @return list<SinkSpec>
     */
    #[Override]
    public function sinks(): array
    {
        $sinks = [];
        foreach ($this->methods() as $method => $kind) {
            $sinks[] = new SinkSpec(
                'laravel.db.' . $method,
                SinkCallKind::StaticCall,
                self::FACADE,
                $method,
                SinkRole::Query,
                sqlParameter: 0,
                valuesParameter: 1,
                kind: $kind,
            );
            $sinks[] = new SinkSpec(
                'laravel.connection.' . $method,
                SinkCallKind::Method,
                self::CONNECTION,
                $method,
                SinkRole::Query,
                sqlParameter: 0,
                valuesParameter: 1,
                kind: $kind,
            );
        }

        foreach ($this->builderMethods() as $method => $kind) {
            foreach (['query' => 'Illuminate\\Database\\Query\\Builder', 'eloquent' => 'Illuminate\\Database\\Eloquent\\Builder', 'model' => 'Illuminate\\Database\\Eloquent\\Model'] as $type => $class) {
                $sinks[] = new SinkSpec('laravel.' . $type . '.' . $method, SinkCallKind::Method, $class, $method, SinkRole::Modelled, kind: $kind, model: 'laravel.builder');
            }
            $sinks[] = new SinkSpec('laravel.model.static.' . $method, SinkCallKind::StaticCall, 'Illuminate\\Database\\Eloquent\\Model', $method, SinkRole::Modelled, kind: $kind, model: 'laravel.builder');
        }

        return $sinks;
    }

    /**
     * Laravel semantics are contributed only when this extension is enabled.
     */
    public function models(Model\ModelContext $context): Model\ModelSet
    {
        $calls = new CallModel($context, $this->dialects);

        return new Model\ModelSet(
            calls: [$calls->evaluate(...)],
            queries: ['laravel.builder' => new BuilderQueries($context->index, $this->dialects)],
            classRelations: [$calls->matchesClass(...)],
        );
    }

    /**
     * Execution calls, including explicitly incomplete compound operations.
     *
     * @return array<string, StatementKind|null>
     */
    public function builderMethods(): array
    {
        $methods = array_fill_keys(['get', 'first', 'firstOrFail', 'find', 'findOrFail', 'sole', 'all', 'pluck', 'value', 'count', 'sum', 'avg', 'min', 'max', 'exists', 'doesntExist', 'paginate', 'simplePaginate', 'cursorPaginate', 'chunk', 'each', 'cursor', 'lazy'], StatementKind::Select);

        return $methods + [
            'insert' => StatementKind::Insert,
            'insertOrIgnore' => StatementKind::Insert,
            'insertGetId' => StatementKind::Insert,
            'upsert' => StatementKind::Insert,
            'update' => StatementKind::Update,
            'increment' => StatementKind::Update,
            'decrement' => StatementKind::Update,
            'delete' => StatementKind::Delete,
            'forceDelete' => StatementKind::Delete,
            'restore' => StatementKind::Update,
            'create' => StatementKind::Insert,
            'save' => null,
            'firstOrCreate' => null,
            'updateOrCreate' => null,
        ];
    }

    /**
     * The connection methods that take raw SQL, and the kind each implies.
     *
     * @return array<string, StatementKind|null>
     */
    public function methods(): array
    {
        return [
            'select' => StatementKind::Select,
            'selectOne' => StatementKind::Select,
            'scalar' => StatementKind::Select,
            'insert' => StatementKind::Insert,
            'update' => StatementKind::Update,
            'delete' => StatementKind::Delete,
            'statement' => null,
            'unprepared' => null,
        ];
    }

    /**
     * The extension declares no globals.
     *
     * @return array<string, string>
     */
    #[Override]
    public function globals(): array
    {
        return [];
    }
}

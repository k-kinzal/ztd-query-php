<?php

declare(strict_types=1);

namespace SqlCatalog\Extension;

use Override;
use SqlCatalog\Sql\StatementKind;

/**
 * Laravel's database facade and connection, where the statement is still written as SQL.
 *
 * The query builder and Eloquent assemble their SQL at runtime and are out of
 * reach of a source-level analyzer; what this extension catalogues is the raw
 * SQL a Laravel application still writes by hand.
 *
 * @visibility root
 */
final class LaravelExtension implements ExtensionInterface
{
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
        return 'Laravel raw SQL through the DB facade and Illuminate connections';
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

        return $sinks;
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

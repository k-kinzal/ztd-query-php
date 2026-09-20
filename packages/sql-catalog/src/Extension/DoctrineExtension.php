<?php

declare(strict_types=1);

namespace SqlCatalog\Extension;

use Override;
use SqlCatalog\Sql\StatementKind;

/**
 * Doctrine DBAL connections, where the statement is written as SQL.
 *
 * @visibility root
 */
final class DoctrineExtension implements ExtensionInterface
{
    private const CONNECTION = 'Doctrine\DBAL\Connection';

    private const STATEMENT = 'Doctrine\DBAL\Statement';

    /**
     * The name the command line selects this extension by.
     */
    #[Override]
    public function name(): string
    {
        return 'doctrine';
    }

    /**
     * What the extension covers.
     */
    #[Override]
    public function description(): string
    {
        return 'Doctrine DBAL connections and prepared statements';
    }

    /**
     * The Doctrine calls that carry a statement or bind a value to one.
     *
     * @return list<SinkSpec>
     */
    #[Override]
    public function sinks(): array
    {
        $sinks = [
            new SinkSpec(
                'doctrine.prepare',
                SinkCallKind::Method,
                self::CONNECTION,
                'prepare',
                SinkRole::Prepare,
                sqlParameter: 0,
                handleType: self::STATEMENT,
            ),
            new SinkSpec(
                'doctrine.statement.bindValue',
                SinkCallKind::Method,
                self::STATEMENT,
                'bindValue',
                SinkRole::Bind,
                nameParameter: 0,
                valueParameter: 1,
            ),
            new SinkSpec(
                'doctrine.statement.executeQuery',
                SinkCallKind::Method,
                self::STATEMENT,
                'executeQuery',
                SinkRole::Execute,
                valuesParameter: 0,
            ),
        ];

        foreach ($this->methods() as $method => $kind) {
            $sinks[] = new SinkSpec(
                'doctrine.' . $method,
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
            'executeQuery' => null,
            'executeStatement' => null,
            'executeCacheQuery' => StatementKind::Select,
            'fetchAllAssociative' => StatementKind::Select,
            'fetchAllKeyValue' => StatementKind::Select,
            'fetchAllNumeric' => StatementKind::Select,
            'fetchAssociative' => StatementKind::Select,
            'fetchNumeric' => StatementKind::Select,
            'fetchFirstColumn' => StatementKind::Select,
            'fetchOne' => StatementKind::Select,
            'iterateAssociative' => StatementKind::Select,
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

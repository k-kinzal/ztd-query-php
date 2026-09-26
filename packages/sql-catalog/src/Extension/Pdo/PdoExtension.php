<?php

declare(strict_types=1);

namespace SqlCatalog\Extension\Pdo;

use Override;
use SqlCatalog\Core\Extension\ExtensionInterface;
use SqlCatalog\Core\Extension\SinkCallKind;
use SqlCatalog\Core\Extension\SinkRole;
use SqlCatalog\Core\Extension\SinkSpec;

/**
 * The PDO API, which every other extension's statements eventually reach.
 *
 * @visibility root
 */
final class PdoExtension implements ExtensionInterface
{
    /**
     * The name the command line selects this extension by.
     */
    #[Override]
    public function name(): string
    {
        return 'pdo';
    }

    /**
     * What the extension covers.
     */
    #[Override]
    public function description(): string
    {
        return 'PDO and PDOStatement, including drop-in subclasses of them';
    }

    /**
     * The PDO calls that carry a statement or bind a value to one.
     *
     * @return list<SinkSpec>
     */
    #[Override]
    public function sinks(): array
    {
        return [
            new SinkSpec('pdo.query', SinkCallKind::Method, 'PDO', 'query', SinkRole::Query, sqlParameter: 0),
            new SinkSpec('pdo.exec', SinkCallKind::Method, 'PDO', 'exec', SinkRole::Query, sqlParameter: 0),
            new SinkSpec(
                'pdo.prepare',
                SinkCallKind::Method,
                'PDO',
                'prepare',
                SinkRole::Prepare,
                sqlParameter: 0,
                handleType: 'PDOStatement',
            ),
            new SinkSpec(
                'pdo.statement.execute',
                SinkCallKind::Method,
                'PDOStatement',
                'execute',
                SinkRole::Execute,
                valuesParameter: 0,
            ),
            new SinkSpec(
                'pdo.statement.bindValue',
                SinkCallKind::Method,
                'PDOStatement',
                'bindValue',
                SinkRole::Bind,
                nameParameter: 0,
                valueParameter: 1,
            ),
            new SinkSpec(
                'pdo.statement.bindParam',
                SinkCallKind::Method,
                'PDOStatement',
                'bindParam',
                SinkRole::Bind,
                nameParameter: 0,
                valueParameter: 1,
            ),
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

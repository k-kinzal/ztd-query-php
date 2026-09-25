<?php

declare(strict_types=1);

namespace SqlCatalog\Extension\Mysqli;

use Override;
use SqlCatalog\Core\Extension\ExtensionInterface;
use SqlCatalog\Core\Extension\SinkCallKind;
use SqlCatalog\Core\Extension\SinkRole;
use SqlCatalog\Core\Extension\SinkSpec;

/**
 * The mysqli API, in both its object and its procedural form.
 *
 * @visibility root
 */
final class MysqliExtension implements ExtensionInterface
{
    /**
     * The name the command line selects this extension by.
     */
    #[Override]
    public function name(): string
    {
        return 'mysqli';
    }

    /**
     * What the extension covers.
     */
    #[Override]
    public function description(): string
    {
        return 'mysqli and mysqli_stmt, including the mysqli_* functions';
    }

    /**
     * The mysqli calls that carry a statement or bind a value to one.
     *
     * @return list<SinkSpec>
     */
    #[Override]
    public function sinks(): array
    {
        return array_merge($this->methodSinks(), $this->functionSinks());
    }

    /**
     * The calls written on a mysqli or mysqli_stmt object.
     *
     * @return list<SinkSpec>
     */
    public function methodSinks(): array
    {
        return [
            new SinkSpec('mysqli.query', SinkCallKind::Method, 'mysqli', 'query', SinkRole::Query, sqlParameter: 0),
            new SinkSpec('mysqli.real_query', SinkCallKind::Method, 'mysqli', 'real_query', SinkRole::Query, sqlParameter: 0),
            new SinkSpec('mysqli.multi_query', SinkCallKind::Method, 'mysqli', 'multi_query', SinkRole::Query, sqlParameter: 0),
            new SinkSpec(
                'mysqli.execute_query',
                SinkCallKind::Method,
                'mysqli',
                'execute_query',
                SinkRole::Query,
                sqlParameter: 0,
                valuesParameter: 1,
            ),
            new SinkSpec(
                'mysqli.prepare',
                SinkCallKind::Method,
                'mysqli',
                'prepare',
                SinkRole::Prepare,
                sqlParameter: 0,
                handleType: 'mysqli_stmt',
            ),
            new SinkSpec(
                'mysqli.stmt.bind_param',
                SinkCallKind::Method,
                'mysqli_stmt',
                'bind_param',
                SinkRole::Bind,
                valuesFrom: 1,
            ),
            new SinkSpec(
                'mysqli.stmt.execute',
                SinkCallKind::Method,
                'mysqli_stmt',
                'execute',
                SinkRole::Execute,
                valuesParameter: 0,
            ),
        ];
    }

    /**
     * The calls written as mysqli_* functions.
     *
     * @return list<SinkSpec>
     */
    public function functionSinks(): array
    {
        return [
            new SinkSpec('mysqli.fn.query', SinkCallKind::FunctionCall, null, 'mysqli_query', SinkRole::Query, sqlParameter: 1),
            new SinkSpec('mysqli.fn.real_query', SinkCallKind::FunctionCall, null, 'mysqli_real_query', SinkRole::Query, sqlParameter: 1),
            new SinkSpec('mysqli.fn.multi_query', SinkCallKind::FunctionCall, null, 'mysqli_multi_query', SinkRole::Query, sqlParameter: 1),
            new SinkSpec(
                'mysqli.fn.execute_query',
                SinkCallKind::FunctionCall,
                null,
                'mysqli_execute_query',
                SinkRole::Query,
                sqlParameter: 1,
                valuesParameter: 2,
            ),
            new SinkSpec(
                'mysqli.fn.prepare',
                SinkCallKind::FunctionCall,
                null,
                'mysqli_prepare',
                SinkRole::Prepare,
                sqlParameter: 1,
                handleType: 'mysqli_stmt',
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

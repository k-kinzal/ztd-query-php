<?php

declare(strict_types=1);

namespace SqlCatalog\Extension;

/**
 * A named set of database calls the analyzer should recognise.
 *
 * Support for a framework or an ORM is a matter of naming the calls it uses to
 * reach the database; nothing else about the analysis changes.
 *
 * @visibility root
 */
interface ExtensionInterface
{
    /**
     * The name the command line selects the extension by.
     */
    public function name(): string;

    /**
     * What the extension covers, shown in the command line help.
     */
    public function description(): string;

    /**
     * The calls the extension recognises.
     *
     * @return list<SinkSpec>
     */
    public function sinks(): array;
}

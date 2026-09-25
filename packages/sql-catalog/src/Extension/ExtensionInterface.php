<?php

declare(strict_types=1);

namespace SqlCatalog\Extension;

/**
 * A named set of database calls the analyzer should recognise.
 *
 * Raw APIs declare sinks and globals. Extensions implementing ModelProviderInterface
 * additionally register source-level call transformations and statement compilers.
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

    /**
     * The global variables the extension knows the class of.
     *
     * A framework that hands its database handle out through a global leaves
     * nothing in the source for the analyzer to read the type from, so the
     * extension that knows the framework is what says the name stands for it.
     *
     * @return array<string, string> Class names, keyed by the variable name written without its `$`
     */
    public function globals(): array;
}

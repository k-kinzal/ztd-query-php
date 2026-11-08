<?php

declare(strict_types=1);

namespace Tests\Fake;

use SqlCatalog\Core\Extension\ExtensionInterface;
use SqlCatalog\Core\Extension\SinkCallKind;
use SqlCatalog\Core\Extension\SinkRole;
use SqlCatalog\Core\Extension\SinkSpec;

/**
 * An extension that treats chosen application calls as queries, so that what
 * their first argument resolves to shows up in the catalog.
 */
final class ProbeExtension implements ExtensionInterface
{
    /**
     * The name the extension is selected by.
     */
    public const NAME = 'probe';

    /**
     * @param list<array{string, string, string|null, string}> $calls Sink ID, call kind (function, method or static), class and name
     */
    public function __construct(private readonly array $calls)
    {
    }

    /**
     * The name the extension is selected by.
     */
    public function name(): string
    {
        return self::NAME;
    }

    /**
     * What the extension covers.
     */
    public function description(): string
    {
        return 'Application calls whose first argument is read as a statement';
    }

    /**
     * One query sink for each chosen call, reading the statement from its first argument.
     *
     * @return list<SinkSpec>
     */
    public function sinks(): array
    {
        return array_map(
            static fn (array $call): SinkSpec => new SinkSpec(
                $call[0],
                match ($call[1]) {
                    'method' => SinkCallKind::Method,
                    'static' => SinkCallKind::StaticCall,
                    default => SinkCallKind::FunctionCall,
                },
                $call[2],
                $call[3],
                SinkRole::Query,
                sqlParameter: 0,
            ),
            $this->calls,
        );
    }

    /**
     * No global variables are known.
     *
     * @return array<string, string>
     */
    public function globals(): array
    {
        return [];
    }
}

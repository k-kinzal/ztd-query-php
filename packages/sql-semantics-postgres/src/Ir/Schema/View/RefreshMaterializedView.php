<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\View;

use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * An ordinary refresh of a persistent materialized view.
 *
 * @visibility public
 * @example Making a materialized view unscannable
 *     $name = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\View\ViewName(new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('app'), new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('report'));
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\View\RefreshMaterializedView($name, false))->toString() // => 'REFRESH MATERIALIZED VIEW "app"."report" WITH NO DATA'
 */
final class RefreshMaterializedView implements SemanticsIR
{
    use Snapshot;

    /**
     * Fixes the exact persistent identity and native population policy.
     */
    public function __construct(
        public readonly ViewName $name,
        public readonly bool $withData = true,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
    ) {
    }

    /**
     * Returns the fixed native language profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8/standard-conforming-strings/native';
    }

    /**
     * Emits the refresh for this exact stored definition.
     */
    public function toString(): string
    {
        return 'REFRESH MATERIALIZED VIEW ' . $this->name->quoted() . ($this->withData ? ' WITH DATA' : ' WITH NO DATA');
    }
}

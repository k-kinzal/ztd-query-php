<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Truncate;

use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * An ordered native clearing operation over persistent PostgreSQL tables.
 *
 * @visibility public
 * @example Preserving the default sequence and dependency policies
 *     $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
 *     $declarations = $semantics->context(['CREATE TABLE app.report (id integer)']);
 *     $semantics->analyze('TRUNCATE app.report', $declarations)->toString() // => 'TRUNCATE TABLE "app"."report" CONTINUE IDENTITY RESTRICT'
 */
final class TruncateTable implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<TruncateTarget> Target occurrences in native order
     */
    public readonly array $targets;

    /**
     * Owns the occurrence list and both dependency and identity policies.
     *
     * @param list<TruncateTarget> $targets All occurrences, including repeated names
     */
    public function __construct(
        array $targets,
        public readonly bool $restartIdentity = false,
        public readonly bool $cascade = false,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
    ) {
        $this->targets = Check::listOf($targets, TruncateTarget::class, 'TRUNCATE requires a nonempty list of checked table targets.', 1);
    }

    /**
     * Identifies the selected native release and lexical profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8/standard-conforming-strings/native';
    }

    /**
     * Emits every occurrence in native order and both explicit policies.
     */
    public function toString(): string
    {
        $targets = [];
        foreach ($this->targets as $target) {
            $targets[] = $target->toString();
        }

        return 'TRUNCATE TABLE ' . implode(', ', $targets)
            . ($this->restartIdentity ? ' RESTART IDENTITY' : ' CONTINUE IDENTITY')
            . ($this->cascade ? ' CASCADE' : ' RESTRICT');
    }
}

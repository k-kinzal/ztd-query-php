<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Rows\With;

use SqlSemantics\Platform\MySql\Ir\Binding\ProjectionMode;
use SqlSemantics\Platform\MySql\Ir\Rows\LocalProjection;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A complete ordinary WITH with ordered local definitions and explicit reads.
 *
 * @visibility public
 * @example Resolving a read of the second definition
 *     $ir = (new \SqlSemantics\Platform\MySql\Semantics())->analyze('WITH a AS (SELECT 1 AS n), b AS (SELECT n FROM a) SELECT n FROM b');
 *     $ir instanceof \SqlSemantics\Platform\MySql\Ir\Rows\With\WithProjection // => true
 */
final class WithProjection implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<WithDefinition> The ordered lexical declarations
     */
    public readonly array $definitions;

    /**
     * Checks each definition in its available prefix and then closes the final body.
     * @param list<WithDefinition> $definitions Ordered local definitions
     */
    public function __construct(
        array $definitions,
        public readonly Select|LocalProjection|WithRead $body,
        public readonly GrammarRelease $release = GrammarRelease::MySql910,
        public readonly ProjectionMode $mode = ProjectionMode::Empty,
    ) {
        Check::input($release !== GrammarRelease::MySql5651 && $release !== GrammarRelease::MySql5744, 'WITH requires MySQL 8.0 or later.');
        $checked = Check::listOf($definitions, WithDefinition::class, 'WITH needs a nonempty ordered definition list.', 1);
        $scope = new WithScope();
        $headings = [];
        foreach ($checked as $definition) {
            $headings[] = $scope->width($definition->body, $headings, $this->profileId());
        }
        $scope->width($body, $headings, $this->profileId());
        $this->definitions = $checked;
    }

    /**
     * Answers the final result width.
     */
    public function width(): int
    {
        return count($this->body->columns);
    }

    /**
     * Identifies the common native release and lexical profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8mb4/lower-case-table-names=0/sql-mode=' . $this->mode->value . '/native/derived-merge=on';
    }

    /**
     * Emits the complete definitions and their final consumer.
     */
    public function toString(): string
    {
        return (new WithSql())->render($this);
    }
}

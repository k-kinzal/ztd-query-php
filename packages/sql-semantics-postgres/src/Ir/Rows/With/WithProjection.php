<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Rows\With;

use SqlSemantics\Platform\PostgreSql\Ir\Binding\ParameterSignature;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\LocalProjection;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesQuery;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A complete ordinary WITH with ordered local definitions and explicit reads.
 *
 * @visibility public
 * @example Resolving a read of the second definition
 *     $ir = (new \SqlSemantics\Platform\PostgreSql\Semantics())->analyze('WITH a AS (SELECT 1 AS n), b AS (SELECT n FROM a) SELECT n FROM b');
 *     $ir instanceof \SqlSemantics\Platform\PostgreSql\Ir\Rows\With\WithProjection // => true
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
        public readonly Select|LocalProjection|ValuesQuery|WithRead $body,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
    ) {
        $checked = Check::listOf($definitions, WithDefinition::class, 'WITH needs a nonempty ordered definition list.', 1);
        $scope = new WithScope();
        $headings = [];
        $independent = [];
        foreach ($checked as $definition) {
            $query = $definition->body;
            $headings[] = $scope->width($query, $headings, $this->profileId());
            if (!$query instanceof WithRead) {
                $independent[] = $query;
            }
        }
        $scope->width($body, $headings, $this->profileId());
        if (!$body instanceof WithRead) {
            $independent[] = $body;
        }
        ParameterSignature::checkQueries($independent);
        $this->definitions = $checked;
    }

    /**
     * Answers the final result width.
     */
    public function width(): int
    {
        return $this->body instanceof ValuesQuery ? count($this->body->rows[0]->cells) : count($this->body->columns);
    }

    /**
     * Identifies the common native release and lexical profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8/standard-conforming-strings/native';
    }

    /**
     * Emits the complete definitions and their final consumer.
     */
    public function toString(): string
    {
        return (new WithSql())->render($this);
    }
}

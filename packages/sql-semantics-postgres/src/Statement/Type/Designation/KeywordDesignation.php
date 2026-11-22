<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Type\Designation;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\TypeFact;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeDesignation;

/**
 * A type spelled with a keyword that takes no modifier: INT, INTEGER, SMALLINT, BIGINT, REAL, DOUBLE PRECISION, BOOLEAN or JSON.
 *
 * Rule: PG-TYPE-KEYWORD-001. The spelling always denotes the `pg_catalog`
 * type, whatever the search path. Facts: `Known`.
 * Source: https://www.postgresql.org/docs/17/datatype.html#DATATYPE-TABLE. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class KeywordDesignation implements TypeDesignation
{
    use Snapshot;

    /**
     * @param TypeKeyword $keyword The spelling
     */
    public function __construct(public readonly TypeKeyword $keyword)
    {
    }

    /**
     * Answers the catalog type of the spelling.
     */
    public function typeFact(AnalysisContext $context, bool $constant): TypeFact
    {
        return new Known($this->keyword->builtin());
    }

    /**
     * Answers the catalog name of the type.
     */
    public function catalogName(): Name
    {
        return new Name($this->keyword->builtin()->value);
    }

    /**
     * Derives nothing: the spelling holds no expression.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the keywords.
     */
    public function render(Output $out): void
    {
        $out->keyword(...explode(' ', $this->keyword->value));
    }
}

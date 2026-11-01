<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Source\Span;

/**
 * A MySQL input containing no SQL statement.
 * The client protocol decides whether empty input is allowed for its command.
 *
 * @visibility public
 * @example Reconstructing empty input
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('')->toString() // => ''
 */
final class EmptyStatement extends SqlStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * Constructs an empty input with optional language information and location.
     */
    public function __construct(?StatementSemantics $statementInformation = null, public readonly ?Span $inputSource = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
    }

    /**
     * Empty input declares no relation, output field or condition.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Empty input contributes no SQL tokens.
     */
    public function render(Output $out): void
    {
    }
}

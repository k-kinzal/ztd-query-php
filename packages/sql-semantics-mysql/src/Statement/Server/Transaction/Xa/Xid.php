<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\Server\Magnitudes;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedXaIdentifier;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;

/**
 * The identifier of an XA transaction: `gtrid [, bqual [, formatID]]`.
 *
 * Mirrors XID. The global transaction identifier and the branch qualifier
 * are strings of at most 64 bytes each, written as quoted strings or as
 * hexadecimal or bit literals; the format identifier is an unsigned number
 * and defaults to 1. Requests retain every operand even when it exceeds those
 * limits. Derivation records the release-specific input condition so execution
 * can refuse the complete statement (MYSQL-SERVER-MAGNITUDE-001).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/xa-statements.html.
 *
 * @visibility public
 * @example Holding the three parts of an identifier
 *     $xid = new \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\Xid(new \SqlSemantics\Platform\MySql\Statement\Literal\Text('g'), new \SqlSemantics\Platform\MySql\Statement\Literal\Text('b'), new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('7'));
 *     [$xid->transaction->value, $xid->branch?->value, $xid->format?->text] // => ['g', 'b', '7']
 */
final class Xid implements Node
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Text $transaction The global transaction identifier
     * @param Text|null $branch The branch qualifier, when written
     * @param Numeral|null $format The format identifier, when written; it needs a branch qualifier
     * @throws InvalidConstruction When the format is written without a branch qualifier
     */
    public function __construct(public readonly Text $transaction, public readonly ?Text $branch = null, public readonly ?Numeral $format = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        Check::input($format === null || $branch !== null, 'A format identifier follows a branch qualifier.');
    }

    /**
     * Records the input condition while retaining the complete identifier.
     */
    public function derive(Derivation $derivation): void
    {
        $problem = $this->problem($derivation->context->profile->grammar);
        if ($problem !== null) {
            $derivation->report($problem);
            $derivation->warn(new ParseFailure($problem, true));
        }
    }

    /**
     * Answers the byte-length or numeric condition at its diagnostic boundary.
     * The source position affects error excerpts only, never the size checks.
     */
    public function problem(GrammarRelease $release): ?RefusedXaIdentifier
    {
        $magnitudes = new Magnitudes();
        if ($magnitudes->bytes($this->transaction) <= 64 && ($this->branch === null || $magnitudes->bytes($this->branch) <= 64) && ($this->format === null || $magnitudes->format($this->format, $release))) {
            return null;
        }
        $end = $this->inputSource === null ? 0 : $this->inputSource->offset + $this->inputSource->length;
        return new RefusedXaIdentifier($this->format === null ? $end : ($this->format->inputSource->offset ?? 0), $this->format === null);
    }

    /**
     * Writes the parts separated by commas.
     */
    public function render(Output $out): void
    {
        $out->node($this->transaction);
        if ($this->branch !== null) {
            $out->symbol(',')->node($this->branch);
        }
        if ($this->format !== null) {
            $out->symbol(',')->node($this->format);
        }
    }
}

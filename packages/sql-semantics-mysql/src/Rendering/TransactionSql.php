<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rendering;

use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

/**
 * Reconstructs transaction control from the concrete statement's operands.
 *
 * Every consuming statement owns its language and optional client encoding.
 * This trait retains no state or analysis indexes.
 *
 * @phpstan-require-implements \SqlSemantics\Platform\MySql\Statement\Server\Transaction\TransactionStatement
 * @visibility SqlSemantics\Platform\MySql
 */
trait TransactionSql
{
    /**
     * Reconstructs this statement from its values and selected language.
     */
    public function toString(): string
    {
        $out = new Output(new Codec($this->profile->grammar, $this->profile->lexical, $this->client));
        $this->render($out);
        return (new Lexical())->join($out->pieces()) . $out->trailing();
    }

    /**
     * Answers the selected language without retaining an analysis environment.
     */
    public function profile(): LanguageProfile
    {
        return $this->profile;
    }

    /**
     * Transaction control introduces no schema declarations.
     *
     * @return list<\SqlSemantics\Statement\Declaration\Table>
     */
    public function declarations(): array
    {
        return [];
    }
}

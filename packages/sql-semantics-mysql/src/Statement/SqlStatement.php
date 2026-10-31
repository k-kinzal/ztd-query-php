<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement;

use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Declaration\Table;

/**
 * Publication and reconstruction behavior shared by concrete MySQL statements.
 * Each subclass retains its own SQL operands, constructor constraints and rules.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
abstract class SqlStatement implements Statement
{
    use \SqlSemantics\Statement\Semantics\ResultFields;

    private ?StatementSemantics $requestInformation = null;

    /**
     * Completes this statement during semantic construction.
     */
    public function initializeStatementInformation(StatementSemantics $information): void
    {
        if (isset($this->requestInformation)) {
            Check::input($this->requestInformation === $information, 'A statement occurrence is resolved once.');
            return;
        }
        $this->requestInformation = $information;
    }

    /**
     * Returns this statement's language, result and conditions.
     */
    public function statementInformation(): StatementSemantics
    {
        Check::input(isset($this->requestInformation), 'A statement must be resolved before use.');
        return $this->requestInformation;
    }

    /**
     * Reports whether construction supplied this occurrence's information.
     */
    public function hasStatementInformation(): bool
    {
        return isset($this->requestInformation);
    }
    /**
     * Reconstructs SQL from this statement's own values.
     */
    public function toString(): string
    {
        $information = $this->statementInformation();
        $out = new Output(new Codec($information->profile->grammar, $information->profile->lexical, $information->client));
        $this->render($out);
        $sql = (new Lexical())->join($out->pieces()) . $out->trailing();
        $platform = \SqlSemantics\Contract\Platforms::of($information->profile->grammar->database());
        (new \SqlSemantics\Validation\Spellings($platform->parser($information->profile), $platform->productions($information->profile), $platform->leafKeys($information->profile)))->check((new Lexical())->join($out->canonical()), $sql);
        return $sql;
    }

    /**
     * Returns this statement's language.
     */
    public function profile(): LanguageProfile
    {
        return $this->statementInformation()->profile;
    }

    /**
     * Returns declarations contributed by this definition.
     *
     * @return list<Table>
     */
    public function declarations(): array
    {
        return $this->statementInformation()->declarations;
    }
    /**
 * Returns the result of this statement, if it produces rows.
 */
    public function output(): ?\SqlSemantics\Statement\Fact\QueryFact
    {
        return $this->statementInformation()->output;
    }
    /**
     * Returns conditions that prevent this statement from executing.
     *
     * @return list<\SqlSemantics\Statement\Fact\Diagnostic>
     */
    public function diagnostics(): array
    {
        return $this->statementInformation()->diagnostics;
    }

    /**
     * Returns nonfatal conditions in their emission order.
     *
     * @return list<\SqlSemantics\Statement\Fact\Warning>
     */
    public function warnings(): array
    {
        return $this->statementInformation()->warnings;
    }

    /**
     * Returns warnings with their owning SQL occurrences.
     *
     * @return list<\SqlSemantics\Statement\Fact\Notice>
     */
    public function notices(): array
    {
        return $this->statementInformation()->notices;
    }

}

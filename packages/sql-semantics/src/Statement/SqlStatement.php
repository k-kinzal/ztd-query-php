<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Contract\Platforms;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Fact\Notice;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Semantics\ResultFields;

/**
 * Publication and result access for concrete SQL statement forms.
 * Every subclass owns its SQL operands, invariants and rendering rule.
 *
 * @visibility SqlSemantics
 */
abstract class SqlStatement implements AnalyzedStatement
{
    use ResultFields;

    private ?StatementSemantics $requestInformation = null;

    /**
 * Completes the statement during construction, before publication.
 */
    public function initializeStatementInformation(StatementSemantics $information): void
    {
        if ($this->requestInformation !== null) {
            Check::input($this->requestInformation === $information, 'A statement occurrence is resolved once.');
            return;
        }
        $this->requestInformation = $information;
    }

    /**
 * Returns the result and conditions belonging to this statement.
 */
    public function statementInformation(): StatementSemantics
    {
        Check::input($this->requestInformation !== null, 'A statement must be resolved before use.');
        return $this->requestInformation;
    }

    /**
 * Reports whether construction supplied this statement's information.
 */
    public function hasStatementInformation(): bool
    {
        return $this->requestInformation !== null;
    }

    /**
 * Reconstructs SQL from the operands of this concrete statement.
 */
    public function toString(): string
    {
        $profile = $this->profile();
        $platform = Platforms::of($profile->grammar->database());
        $output = new Output($platform->codec($profile));
        $this->render($output);
        $sql = (new Lexical())->join($output->pieces()) . $output->trailing();
        (new \SqlSemantics\Validation\Spellings($platform->parser($profile), $platform->productions($profile), $platform->leafKeys($profile)))->check((new Lexical())->join($output->canonical()), $sql);
        return $sql;
    }

    /**
 * Returns the language of this statement.
 */
    public function profile(): LanguageProfile
    {
        return $this->statementInformation()->profile;
    }

    /**
     * Returns declarations introduced by this definition.
     * @return list<Table>
     */
    public function declarations(): array
    {
        return $this->statementInformation()->declarations;
    }

    /**
 * Returns the result of this statement, if it produces rows.
 */
    public function output(): ?QueryFact
    {
        return $this->statementInformation()->output;
    }
    /**
     * Returns conditions that prevent this statement from executing.
     *
     * @return list<Fact\Diagnostic>
     */
    public function diagnostics(): array
    {
        return $this->statementInformation()->diagnostics;
    }

    /**
     * Returns nonfatal conditions in their emission order.
     *
     * @return list<Fact\Warning>
     */
    public function warnings(): array
    {
        return $this->statementInformation()->warnings;
    }

    /**
     * Returns warnings with their owning SQL occurrences.
     *
     * @return list<Notice>
     */
    public function notices(): array
    {
        return $this->statementInformation()->notices;
    }

}

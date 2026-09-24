<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Account;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\ClientAccount;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Account\AccountForms;
use SqlSemantics\Model\Definition\Account\Alteration\AuthenticationFactor;
use SqlSemantics\Model\Definition\Account\Identification\IdentificationOperands;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;

/**
 * ALTER USER account n FACTOR FINISH REGISTRATION SET CHALLENGE_RESPONSE AS 'response': completes device registration with the client's response.
 * @visibility public
 * @example Reading the challenge response spelling
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("ALTER USER a 2 FACTOR FINISH REGISTRATION SET CHALLENGE_RESPONSE AS 'resp'");
 *     $statement->challengeResponse->text // => "'resp'"
 */
final class FinishRegistrationStatement extends BoundStatement
{
    /**
     * The response is recorded as a text or hexadecimal literal and never verified.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(Origin $origin, public readonly AccountName|CurrentAccount|ClientAccount $account, public readonly AuthenticationFactor $factor, public readonly Literal $challengeResponse)
    {
        AccountForms::modern($origin, 'Factor registration');
        IdentificationOperands::hash($challengeResponse);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->account, $this->factor, $this->challengeResponse);
    }

    /**
     * Replaces the registering account.
     */
    public function withAccount(AccountName|CurrentAccount|ClientAccount $account): self
    {
        return $this->changed(new self($this->origin, $account, $this->factor, $this->challengeResponse));
    }

    /**
     * Replaces the factor being registered.
     */
    public function withFactor(AuthenticationFactor $factor): self
    {
        return $this->changed(new self($this->origin, $this->account, $factor, $this->challengeResponse));
    }

    /**
     * Replaces the client's challenge response.
     */
    public function withChallengeResponse(Literal $challengeResponse): self
    {
        return $this->changed(new self($this->origin, $this->account, $this->factor, $challengeResponse));
    }
}

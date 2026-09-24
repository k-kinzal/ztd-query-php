<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Privilege;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Account\AccountDefinition;
use SqlSemantics\Model\Definition\Account\AccountForms;
use SqlSemantics\Model\Definition\Privilege\PrivilegeOperands;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;

/**
 * GRANT PROXY ON account TO accounts: lets the recipients act as the proxied account.
 * @visibility public
 * @example Reading the proxied account and recipients
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("GRANT PROXY ON 'p'@'h' TO a, b WITH GRANT OPTION");
 *     [$statement->proxied->username, count($statement->grantees), $statement->withGrantOption] // => ['p', 2, true]
 */
final class GrantProxyStatement extends BoundStatement
{
    /**
     * @param non-empty-list<AccountName|CurrentAccount|AccountDefinition> $grantees Ordered recipients; legacy releases may attach one credential each
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(Origin $origin, public readonly AccountName|CurrentAccount $proxied, public readonly array $grantees, public readonly bool $withGrantOption = false)
    {
        AccountForms::mysql($origin);
        PrivilegeOperands::grantees($origin, $grantees, true);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Grant;
    }

    /**
     * Retains the proxy grant while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->proxied, $this->grantees, $this->withGrantOption);
    }

    /**
     * Replaces the proxied account.
     */
    public function withProxied(AccountName|CurrentAccount $proxied): self
    {
        return $this->changed(new self($this->origin, $proxied, $this->grantees, $this->withGrantOption));
    }

    /**
     * Replaces the recipients.
     * @param non-empty-list<AccountName|CurrentAccount|AccountDefinition> $grantees Replacement recipients
     */
    public function withGrantees(array $grantees): self
    {
        return $this->changed(new self($this->origin, $this->proxied, $grantees, $this->withGrantOption));
    }

    /**
     * Replaces whether recipients may grant the proxy privilege onward.
     */
    public function withWithGrantOption(bool $withGrantOption): self
    {
        return $this->changed(new self($this->origin, $this->proxied, $this->grantees, $withGrantOption));
    }
}

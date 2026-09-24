<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Server\Replication;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Replication\CredentialOption;
use SqlSemantics\Model\Configuration\Replication\ReplicationCredential;
use SqlSemantics\Model\Configuration\Replication\ReplicationRelease;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Starts group replication on this member, optionally with distributed recovery credentials.
 * @visibility public
 * @example Binding the operation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("START GROUP_REPLICATION USER = 'repl', PASSWORD = 'secret'");
 *     $statement->credentials[1]->option->value // => 'PASSWORD'
 */
final class StartGroupReplicationStatement extends BoundStatement
{
    /**
     * @param list<ReplicationCredential> $credentials USER, PASSWORD and DEFAULT_AUTH in request order; a later value replaces an earlier one
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly array $credentials = [])
    {
        ReplicationRelease::require($origin, 'START GROUP_REPLICATION', 50700);
        Collections::objects($credentials, ReplicationCredential::class);
        if ($credentials !== []) {
            ReplicationRelease::require($origin, 'START GROUP_REPLICATION credentials', 80000);
        }
        foreach ($credentials as $credential) {
            $text = $credential->text();
            $invalid = match ($credential->option) {
                CredentialOption::PluginDir => true,
                CredentialOption::User => $text === '',
                CredentialOption::Password => strlen($text) > 32,
                CredentialOption::DefaultAuth => false,
            };
            if ($invalid || str_contains($text, "\n")) {
                throw new InvalidStructure('Group replication credentials are a nonempty USER, a PASSWORD of at most 32 characters and DEFAULT_AUTH, each on one line.');
            }
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Start;
    }

    /**
     * Retains the credentials when changing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->credentials);
    }

    /**
     * Replaces the recovery credentials.
     * @param list<ReplicationCredential> $credentials
     */
    public function withCredentials(array $credentials): self
    {
        return $this->changed(new self($this->origin, $credentials));
    }
}

<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit\Password;

use Container\Endpoint;
use Fuzz\Target\Password\Retention;
use Fuzz\Target\RandomPasswords;
use MySqlMemory\Session\Session;
use PDO;
use SqlSemantics\Platform\MySql\Statement\Account\SetPassword;
use SqlSemantics\Platform\MySql\Statement\Name\CurrentUser;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Scalar;

/**
 * Checks the existing random-password relationships after original direct execution.
 * The generated value is replaced only after each server validates its own account,
 * configured length, wrong-password refusal, correct replacement and optional retention.
 */
final class Contract
{
    /**
     * Keeps independent native and memory observations of the initial account state.
     */
    public function __construct(public readonly PDO $native, public readonly Session $session, public readonly RandomPasswords $nativePolicy, public readonly RandomPasswords $memoryPolicy)
    {
    }

    /**
     * Selects an isolated random SET PASSWORD for the current account, without REPLACE.
     * A separate native connection keeps validation reads out of the target session history.
     */
    public static function capture(Statement|Scalar $node, Endpoint $endpoint, Session $session): ?self
    {
        if (!$node instanceof SetPassword || $node->password !== null || $node->replace !== null || ($node->user !== null && !$node->user instanceof CurrentUser) || str_starts_with($session->instance->version, '5.')) {
            return null;
        }
        $native = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port, $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $left = RandomPasswords::capture($native, $node->retainCurrent);
        $right = (new MemoryState())->capture($session, $node->retainCurrent);

        return $left === null || $right === null ? null : new self($native, $session, $left, $right);
    }

    /**
     * @param array<string, mixed> $observation The complete original protocol observation
     * @return array<string, mixed> A copy with only the validated password replaced, or unchanged on refusal
     */
    public function comparable(array $observation, bool $memory): array
    {
        $policy = $memory ? $this->memoryPolicy : $this->nativePolicy;
        $password = $policy->password(self::project($observation));
        if ($password === null) {
            return $observation;
        }
        $verified = $memory ? (new MemoryState())->verifies($this->session, $policy, $password)
            : (($policy->retention === null || $policy->retention->retained($this->native)) && $policy->verifies($this->native, $password) && ($policy->retention === null || $policy->retention->discarded($this->native)));
        $results = $observation['results'] ?? null;
        if (!$verified || !is_array($results) || !is_array($results[0] ?? null) || !is_array($results[0]['rows'] ?? null) || !is_array($results[0]['rows'][0] ?? null)) {
            return $observation;
        }
        $results[0]['rows'][0][2] = '{' . RandomPasswords::CONTRACT . '}';
        $observation['results'] = $results;
        $observation['contracts'] = $policy->retention === null ? [RandomPasswords::CONTRACT] : [RandomPasswords::CONTRACT, Retention::CONTRACT];

        return $observation;
    }

    /**
     * Adapts only column labels for the shared password-shape validator.
     * The original protocol metadata remains in the observation compared by the campaign.
     *
     * @param array<string, mixed> $observation The complete direct observation
     * @return array<string, mixed> The shape consumed by the existing PDO contract
     */
    public static function project(array $observation): array
    {
        $results = $observation['results'] ?? null;
        if (!is_array($results) || !is_array($results[0] ?? null) || !is_array($results[0]['columns'] ?? null)) {
            return $observation;
        }
        $columns = [];
        foreach ($results[0]['columns'] as $column) {
            if (!is_array($column) || !is_string($column['name'] ?? null) || !is_string($column['orgname'] ?? null)) {
                return $observation;
            }
            $columns[] = [$column['name'], $column['orgname']];
        }
        $results[0]['columns'] = $columns;
        $observation['results'] = $results;

        return $observation;
    }
}

<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing\Builtin\Spatial;

use Closure;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\Invocation;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\Type\ArgumentDomainMismatch;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Resolves geohash result domains and argument type constraints before evaluation.
 *
 * Binary strings are geometry payloads, not geohash text. Lengths and reference
 * identifiers accept integers and character strings, but not real or decimal
 * domains, even when their runtime value is NULL. Verified on MySQL 5.7 and 8.4.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/spatial-geohash-functions.html.
 *
 * @visibility SqlSemantics\Platform\MySql\Rules\Typing
 */
final class GeohashResults
{
    /**
     * @return array<string, Closure(Invocation): Domain> Native geohash typing rules
     */
    public function rules(): array
    {
        return [
            'ST_GEOHASH' => $this->encoded(...),
            'ST_POINTFROMGEOHASH' => fn (Invocation $call): Domain => $this->decoded($call, 'st_pointfromgeohash'),
            'ST_LATFROMGEOHASH' => fn (Invocation $call): Domain => $this->decoded($call, 'ST_LATFROMGEOHASH'),
            'ST_LONGFROMGEOHASH' => fn (Invocation $call): Domain => $this->decoded($call, 'ST_LONGFROMGEOHASH'),
        ];
    }

    /**
     * Resolves legacy parameter nullability for geohash results.
     * Unbound markers remain nullable; actual non-NULL bindings make these
     * functions non-nullable, including the point-producing decoder.
     *
     * @param list<\SqlSemantics\Statement\Fact\ScalarFact> $facts Resolved argument facts
     */
    public function nullability(string $name, array $facts, \SqlSemantics\Construction\Derivation $derivation, \SqlSemantics\Statement\Type\Nullability $fallback): \SqlSemantics\Statement\Type\Nullability
    {
        if ($derivation->context->profile->grammar !== \SqlSemantics\Contract\GrammarRelease::MySql5744 || !in_array(strtoupper($name), ['ST_GEOHASH', 'ST_LATFROMGEOHASH', 'ST_LONGFROMGEOHASH', 'ST_POINTFROMGEOHASH'], true)) {
            return $fallback;
        }
        $settings = \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings::of($derivation->context);
        $result = \SqlSemantics\Statement\Type\Nullability::NotNull;
        foreach ($facts as $fact) {
            $bound = $fact->resolution instanceof \SqlSemantics\Platform\MySql\Statement\Variable\ParameterBinding && !in_array($fact->resolution->position, $settings->unboundParameters, true)
                && $fact->type instanceof \SqlSemantics\Statement\Type\Known && $fact->type->descriptor instanceof Domain && $fact->type->descriptor->kind !== Kind::Null;
            $result = $result->propagate($bound ? \SqlSemantics\Statement\Type\Nullability::NotNull : $fact->nullability);
        }
        return $result;
    }

    /**
     * Resolves the string result and checks the point and maximum length domains.
     */
    public function encoded(Invocation $call): Domain
    {
        $valid = count($call->domains) === 2 ? $this->point($call) : $this->coordinate($call, 0, 'longitude') && $this->coordinate($call, 1, 'latitude');
        if ($valid) {
            $this->argument($call, count($call->domains) - 1, 'st_geohash', 'geohash max length', true);
        }
        return Domain::string(100, $call->settings->connection, Field::VarString, Coercibility::Coercible);
    }

    /**
     * Resolves point payloads, including the distinct parameter contract of 5.7.
     */
    public function point(Invocation $call): bool
    {
        $domain = $call->domain(0);
        $node = $this->operand($call, 0);
        $parameter = $node instanceof \SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
        $unbound = $parameter && in_array($call->derivation->parameterPosition($node), $call->settings->unboundParameters, true);
        $legacy = $call->derivation->context->profile->grammar === \SqlSemantics\Contract\GrammarRelease::MySql5744;
        $valid = $unbound || $domain->kind === Kind::Null || $this->binary($call, 0) || ($parameter && (!$legacy || $domain->kind !== Kind::String));
        if (!$valid) {
            $call->derivation->report(new ArgumentDomainMismatch(new Name('st_geohash'), 'point'));
        }
        return $valid;
    }

    /**
     * Resolves numeric coordinate roles before maximum length and runtime NULLs.
     */
    public function coordinate(Invocation $call, int $index, string $role): bool
    {
        $domain = $call->domain($index);
        $node = $this->operand($call, $index);
        $legacy = $call->derivation->context->profile->grammar === \SqlSemantics\Contract\GrammarRelease::MySql5744;
        $numeric = in_array($domain->kind, [Kind::Integer, Kind::Decimal, Kind::Double], true);
        $valid = $domain->kind === Kind::Null || (!$this->binary($call, $index) && $numeric && (!$legacy || !$node instanceof \SqlSemantics\Platform\MySql\Statement\Literal\Parameter))
            || ($domain->kind === Kind::String && $this->text($domain, $role))
            || ($legacy && $node instanceof \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral);
        if (!$valid) {
            $call->derivation->report(new ArgumentDomainMismatch(new Name('st_geohash'), $role));
        }
        return $valid;
    }

    /**
     * Resolves either the numeric coordinate or binary point result.
     */
    public function decoded(Invocation $call, string $function): Domain
    {
        $valid = $this->argument($call, 0, $function, 'geohash', false);
        if ($function === 'st_pointfromgeohash') {
            if ($valid) {
                $this->argument($call, 1, $function, 'SRID', true);
            }
            return new Domain(Kind::String, Field::Geometry, 4294967295, 0, false, Collation::binary(), [], Coercibility::Coercible);
        }
        return Domain::double(23);
    }

    /**
     * Reports a type mismatch using resolved domains, independently of runtime values.
     */
    public function argument(Invocation $call, int $index, string $function, string $role, bool $integer): bool
    {
        $domain = $call->domain($index);
        $valid = $domain->kind === Kind::Null || ($integer && $domain->kind === Kind::Integer && !$this->binary($call, $index))
            || ($domain->kind === Kind::String && $this->text($domain, $role));
        if (!$valid) {
            $call->derivation->report(new ArgumentDomainMismatch(new Name($function), $role));
        }
        return $valid;
    }

    /**
     * Distinguishes binary scalar strings from the blob domains of user variables.
     * Hashes and lengths accept blobs in either character set. SRIDs accept
     * binary blobs and character scalar strings, but refuse character blobs.
     * Verified with fresh session variables and computed strings on MySQL 8.4.
     */
    public function text(Domain $domain, string $role): bool
    {
        $blob = in_array($domain->field, [Field::TinyBlob, Field::Blob, Field::MediumBlob, Field::LongBlob], true);
        $binary = $domain->collation->charset->name === 'binary';
        return $role === 'SRID' ? $blob === $binary : $blob || !$binary;
    }

    /**
     * Reads the retained binary-string interpretation independently of value type.
     * Typed NULL assignments preserve this interpretation from the preceding
     * entry, while ordinary assignments derive it from the assigned domain.
     */
    public function binary(Invocation $call, int $index): bool
    {
        $node = $this->operand($call, $index);
        $entry = $node instanceof \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable ? $call->settings->userVariables[strtolower($node->name->value)] ?? null : null;
        if ($entry instanceof \SqlSemantics\Platform\MySql\Statement\Variable\UserVariableEntry && $entry->binaryString !== null) {
            return $entry->binaryString;
        }
        return $call->domain($index)->kind === Kind::String && $call->domain($index)->collation->charset->name === 'binary';
    }

    /**
     * Reads an operand's semantic form through transparent grouping.
     */
    public function operand(Invocation $call, int $index): ?\SqlSemantics\Statement\Scalar
    {
        $node = $call->nodes[$index] ?? null;
        while ($node instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Grouped) {
            $node = $node->operand;
        }
        return $node;
    }
}

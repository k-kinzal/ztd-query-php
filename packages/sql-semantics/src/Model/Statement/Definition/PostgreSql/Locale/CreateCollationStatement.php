<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Locale;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\TypeSystem\Collation\CollationProvider;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Creates a collation from a provider and its locale settings.
 * A libc collation names one locale or both LC_COLLATE and LC_CTYPE; ICU and builtin collations name a locale.
 * @visibility public
 * @example Creating a nondeterministic ICU collation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("CREATE COLLATION IF NOT EXISTS app.ci (provider = icu, locale = 'und-u-ks-level2', deterministic = false)");
 *     $statement->locale // => 'und-u-ks-level2'
 *     $statement->deterministic // => false
 *     $statement->toString() // => 'CREATE COLLATION IF NOT EXISTS "app"."ci"(PROVIDER = \'icu\', LOCALE = \'und-u-ks-level2\', DETERMINISTIC = FALSE)'
 * @example Rejecting ICU rules on a libc collation
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("CREATE COLLATION posix (locale = 'C')");
 *     $statement->withRules('&a < b'); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class CreateCollationStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly QualifiedName $name,
        public readonly CollationProvider $provider,
        public readonly ?string $locale,
        public readonly ?string $lcCollate = null,
        public readonly ?string $lcCtype = null,
        public readonly bool $deterministic = true,
        public readonly ?string $rules = null,
        public readonly ?string $version = null,
        public readonly bool $ifNotExists = false,
    ) {
        TypeSystemInvariant::dialect($origin);
        TypeSystemInvariant::name($name);
        self::settings($provider, $locale, $lcCollate, $lcCtype);
        if ((!$deterministic || $rules !== null) && $provider !== CollationProvider::Icu) {
            throw new InvalidStructure('Only ICU collations can be nondeterministic or carry rules.');
        }
        parent::__construct($origin);
    }

    /**
     * LOCALE excludes LC_COLLATE and LC_CTYPE; a libc collation needs both categories, other providers a locale, and builtin only C or C.UTF-8.
     * @throws InvalidStructure
     */
    public static function settings(CollationProvider $provider, ?string $locale, ?string $lcCollate, ?string $lcCtype): void
    {
        if ($locale !== null && ($lcCollate !== null || $lcCtype !== null)) {
            throw new InvalidStructure('LOCALE cannot be combined with LC_COLLATE or LC_CTYPE.');
        }
        $complete = match ($provider) {
            CollationProvider::Libc => $locale !== null || ($lcCollate !== null && $lcCtype !== null),
            CollationProvider::Icu => $locale !== null,
            CollationProvider::Builtin => in_array($locale, ['C', 'C.UTF-8'], true),
        };
        if (!$complete) {
            throw new InvalidStructure('The collation does not name the locale its provider requires.');
        }
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains every operand while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->provider, $this->locale, $this->lcCollate, $this->lcCtype, $this->deterministic, $this->rules, $this->version, $this->ifNotExists);
    }

    /**
     * Replaces the collation name.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->provider, $this->locale, $this->lcCollate, $this->lcCtype, $this->deterministic, $this->rules, $this->version, $this->ifNotExists));
    }

    /**
     * Replaces the provider.
     */
    public function withProvider(CollationProvider $provider): self
    {
        return $this->changed(new self($this->origin, $this->name, $provider, $this->locale, $this->lcCollate, $this->lcCtype, $this->deterministic, $this->rules, $this->version, $this->ifNotExists));
    }

    /**
     * Replaces or removes the locale.
     */
    public function withLocale(?string $locale): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->provider, $locale, $this->lcCollate, $this->lcCtype, $this->deterministic, $this->rules, $this->version, $this->ifNotExists));
    }

    /**
     * Replaces the LC_COLLATE and LC_CTYPE categories together.
     */
    public function withCategories(?string $lcCollate, ?string $lcCtype): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->provider, $this->locale, $lcCollate, $lcCtype, $this->deterministic, $this->rules, $this->version, $this->ifNotExists));
    }

    /**
     * Chooses whether equal comparisons require identical bytes.
     */
    public function withDeterministic(bool $deterministic): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->provider, $this->locale, $this->lcCollate, $this->lcCtype, $deterministic, $this->rules, $this->version, $this->ifNotExists));
    }

    /**
     * Replaces or removes the ICU tailoring rules.
     */
    public function withRules(?string $rules): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->provider, $this->locale, $this->lcCollate, $this->lcCtype, $this->deterministic, $rules, $this->version, $this->ifNotExists));
    }

    /**
     * Replaces or removes the recorded provider version.
     */
    public function withVersion(?string $version): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->provider, $this->locale, $this->lcCollate, $this->lcCtype, $this->deterministic, $this->rules, $version, $this->ifNotExists));
    }

    /**
     * Replaces the tolerance for an existing collation.
     */
    public function withIfNotExists(bool $ifNotExists): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->provider, $this->locale, $this->lcCollate, $this->lcCtype, $this->deterministic, $this->rules, $this->version, $ifNotExists));
    }
}

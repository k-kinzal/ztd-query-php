<?php

declare(strict_types=1);

namespace MySqlMemory\Variable;

use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Catalog;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Resolves encoding variable values and their assignment-time notices.
 *
 * Variable expressions retain their evaluated spelling: the utf8 alias warns
 * differently from utf8mb3. Numeric values name a collation id. SET NAMES and
 * SET CHARACTER SET use their semantic input notices instead of issuing one
 * notice for each variable they update. Observed on MySQL 5.6, 5.7 and 8.4.
 *
 * @visibility MySqlMemory
 */
final class EncodingSettings
{
    /**
     * @param Context $context The current statement
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Resolves the collation id of an integer assignment, or the written name.
     */
    public function name(int|float|string|\MySqlMemory\Evaluation\ExactDecimal $value, bool $charset): string
    {
        if (is_int($value)) {
            foreach (Catalog::shared()->collations as $collation) {
                if ($collation->id === $value && $collation->availableIn($this->context->modes->release)) {
                    return $charset ? $collation->charset->nameIn($this->context->modes->release) : $collation->nameIn($this->context->modes->release);
                }
            }
        }

        return (string) $value;
    }

    /**
     * Reports the evaluated value's notice before a database-variable notice.
     */
    public function notices(string $variable, int|float|string|\MySqlMemory\Evaluation\ExactDecimal|null $value, ?Domain $domain): void
    {
        if ($value === null || $domain === null) {
            return;
        }
        $variable = strtolower($variable);
        $charset = str_starts_with($variable, 'character_set_');
        if (!$charset && !str_starts_with($variable, 'collation_')) {
            return;
        }
        $name = $this->name($value, $charset);
        if ($charset) {
            $construct = Deprecation::charsetWarning($name);
            $notice = $construct !== null && $construct->warnedIn($this->context->modes->release) ? new Deprecation($construct) : null;
        } else {
            $notice = (new CollationName(new Name($name)))->deprecation($this->context->modes->release);
        }
        if ($notice !== null) {
            $this->context->diagnostics->warning($notice->code(), $notice->message());
        }
    }

    /**
     * Refuses multibyte fixed-width client encodings before updating variables.
     */
    public function client(string $variable, Charset $charset): void
    {
        if ($variable === 'character_set_client' && $charset->minLength() > 1) {
            throw AdministrationError::WrongValueForVariable->error($variable, $charset->nameIn($this->context->modes->release));
        }
    }

    /**
     * Keeps connection, server and database character sets paired with their collation.
     * Client and result encodings are independent. Both GLOBAL and SESSION updates
     * retain the selected scope; assigning a charset selects its release default.
     */
    public function assigned(\MySqlMemory\Session\Variables $variables, string $variable, Scope $scope, string|int|null $value): void
    {
        $charset = str_starts_with($variable, 'character_set_');
        $suffix = substr($variable, $charset ? 14 : 10);
        if (!is_string($value) || !in_array($suffix, ['connection', 'server', 'database'], true) || (!$charset && !str_starts_with($variable, 'collation_'))) {
            return;
        }
        $release = $this->context->modes->release;
        $companion = $charset ? Charset::known($value)->defaultCollation($release)->nameIn($release) : Collation::known($value)->charset->nameIn($release);
        $definition = $variables->catalog->find(($charset ? 'collation_' : 'character_set_') . $suffix);
        if ($definition !== null) {
            if ($scope === Scope::Global) {
                $variables->globals->set($definition, $companion);
            } else {
                $variables->session[$definition->name] = $companion;
            }
        }
    }

    /**
     * Checks an explicit SET NAMES collation against the selected character set.
     */
    public function compatible(string $charset, string $collation): void
    {
        $named = Charset::named($charset);
        $compared = Collation::forName($collation, $this->context->modes->release);
        if ($compared === null) {
            throw \MySqlMemory\Error\Family\SchemaError::UnknownCollation->error($collation);
        }
        if ($named !== null && $compared->charset !== $named) {
            throw \MySqlMemory\Error\Family\SchemaError::CollationCharsetMismatch->error($compared->nameIn($this->context->modes->release), $named->nameIn($this->context->modes->release));
        }
    }
}

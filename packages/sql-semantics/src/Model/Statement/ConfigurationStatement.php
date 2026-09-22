<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement;

use SqlSemantics\Model\BoundStatement;

/**
 * Ordered named SET, RESET, or PRAGMA effects against a fixed context.
 *
 * @example Reading the statement structure
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SET search_path=public');
 *     $statement->settings[0]->name // => ['search_path']
 *
 * @visibility public
 */
final class ConfigurationStatement extends BoundStatement
{
    /**
     * @param list<\SqlSemantics\Model\Expression> $values Ordered values of one setting
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function withValues(\SqlSemantics\Model\Configuration\Setting $setting, array $values): self
    {
        if (!in_array($setting, $this->settings, true) || $setting->action !== 'set' || $setting->values === []) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('The target must be an owned SET value list.');
        }
        \SqlSemantics\Model\Validation\Collections::objects($values, \SqlSemantics\Model\Expression::class);
        return $this->context()->setting($this, $setting, $values);
    }
}

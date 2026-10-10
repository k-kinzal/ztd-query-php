<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Spatial;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Value\Spatial\Wkb;
use SqlSemantics\Contract\GrammarRelease;

/**
 * Reads or replaces the spatial reference identifier without transforming coordinates.
 *
 * Target identifiers are converted and checked before the geometry. The getter warns about
 * an unknown reference system; replacing an identifier can repair such a value. MySQL 5.6
 * reads the identifier from a recognized geometry header without validating its coordinates.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/gis-general-property-functions.html#function_st-srid.
 *
 * @visibility MySqlMemory
 */
final class Srid
{
    /**
     * Answers the current and legacy function names; SQL Semantics checks release-specific arity.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        return [new Routine('ST_SRID', 1, 2, $this->value(...)), new Routine('SRID', 1, 1, $this->value(...))];
    }

    /**
     * Reads an identifier or replaces it while preserving the geometry bytes.
     *
     * @param list<Evaluable> $arguments
     */
    public function value(Frame $frame, array $arguments): int|string|null
    {
        $raw = $arguments[0]->evaluate($frame);
        $target = null;
        if (isset($arguments[1])) {
            $value = $arguments[1]->evaluate($frame);
            $domain = $arguments[1]->domain();
            $target = $value !== null && $domain->kind === \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Decimal
                ? Convert::decimalInteger((string) $value, $frame->context, true)
                : Convert::toInteger($value, $domain, $frame->context, true);
        }
        if (isset($arguments[1]) && $target === null) {
            return null;
        }
        if ($target !== null && ($target < 0 || $target > 4294967295)) {
            throw DataError::DataOutOfRange->error('SRID', 'st_srid');
        }
        if ($raw === null) {
            return null;
        }
        $bytes = (string) Convert::toText($raw, $arguments[0]->domain());
        if ($frame->context->modes->release === GrammarRelease::MySql5651) {
            return $this->legacy($bytes);
        }
        if ($frame->context->modes->release === GrammarRelease::MySql5744) {
            return (new Properties())->geometry($bytes, 'st_srid')->srid;
        }
        $catalog = $frame->context->variables->instance->registry->spatialCatalog;
        if ($target !== null && $target !== 0 && $catalog->name($target) === null) {
            throw SchemaError::SrsNotFound->error($target);
        }
        $geometry = Wkb::read($bytes);
        if ($geometry === null || !$geometry->valid()) {
            throw DataError::GisInvalidData->error('st_srid');
        }
        if ($target !== null) {
            return pack('V', $target) . substr($bytes, 4);
        }
        if ($geometry->srid !== 0 && $catalog->name($geometry->srid) === null) {
            $frame->context->diagnostics->warning(SchemaError::SrsNotFoundWarning, SchemaError::SrsNotFoundWarning->message($geometry->srid));
        }

        return $geometry->srid;
    }

    /**
     * Reads the little-endian identifier of a recognized legacy header, regardless of payload or byte-order marker.
     */
    public function legacy(string $bytes): ?int
    {
        if (strlen($bytes) < 9) {
            return null;
        }
        $offset = 5;
        $type = Wkb::number($bytes, $offset, true);
        $offset = 0;

        return $type !== null && $type >= 1 && $type <= 7 ? Wkb::number($bytes, $offset, true) : null;
    }
}

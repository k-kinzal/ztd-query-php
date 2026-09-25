<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Analysis;

use SqlCatalog\Core\Evaluation\Domain;
use SqlCatalog\Core\Extension\SinkSpec;

/**
 * Attaches the values a call binds to the statements they are bound to.
 *
 * Drivers offer several ways to say the same thing: one array of values, a
 * variadic list, or one name and one value at a time. The binder reads all of
 * them into the same record, so a statement carries what it is given however it
 * was given.
 *
 * @visibility root
 */
final class ValueBinder
{
    private StatementRecorder $recorder;

    /**
     * Wires the binder to the recorder holding the open statements.
     */
    public function __construct(StatementRecorder $recorder)
    {
        $this->recorder = $recorder;
    }

    /**
     * The statements a receiver's handle stands for.
     *
     * @return list<QueryRecord>
     */
    public function openRecords(Domain $receiver): array
    {
        $handle = $receiver->soleObject()?->statementId;

        return $handle === null ? [] : $this->recorder->prepared($handle);
    }

    /**
     * Attaches the values a call binds to every statement it binds them to.
     *
     * @param list<QueryRecord> $records
     * @param list<Domain> $arguments
     */
    public function bindValues(array $records, SinkSpec $sink, array $arguments): void
    {
        foreach ($records as $record) {
            $this->bindOneRecord($record, $sink, $arguments);
        }
    }

    /**
     * Attaches the values a call binds to one statement.
     *
     * @param list<Domain> $arguments
     */
    public function bindOneRecord(QueryRecord $record, SinkSpec $sink, array $arguments): void
    {
        if ($sink->valuesParameter !== null) {
            $array = ($arguments[$sink->valuesParameter] ?? Domain::unknown())->soleArray();
            if ($array !== null) {
                $record->bind($array->positional(), $array->named());
            }

            return;
        }
        if ($sink->valuesFrom !== null) {
            $record->bind(array_slice($arguments, $sink->valuesFrom), []);

            return;
        }
        if ($sink->nameParameter === null || $sink->valueParameter === null) {
            return;
        }
        $key = ($arguments[$sink->nameParameter] ?? Domain::unknown())->soleLiteral();
        $record->bindOne($this->bindingKey($key?->value), $arguments[$sink->valueParameter] ?? Domain::unknown());
    }

    /**
     * The key a single bind writes its value under.
     */
    public function bindingKey(string|int|float|bool|null $written): string|int|null
    {
        if (is_int($written)) {
            return $written;
        }

        return is_string($written) ? $written : null;
    }
}

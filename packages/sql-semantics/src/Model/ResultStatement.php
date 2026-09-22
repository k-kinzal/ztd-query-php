<?php

declare(strict_types=1);

namespace SqlSemantics\Model;

/**
 * An operation producing named result columns.
 * @visibility public
 */
interface ResultStatement
{
    /**
     * @return list<OutputColumn>
     */
    public function resultColumns(): array;


}

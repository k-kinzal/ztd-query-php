<?php

declare(strict_types=1);

namespace SqlFaker\Contract;

interface TerminalRenderer
{
    public function render(TerminalSequence $terminals): TokenSequence;
}

<?php

declare(strict_types=1);

namespace SqlFaker\Contract;

interface TerminationLengthComputer
{
    public function compute(Grammar $grammar): TerminationLengths;
}

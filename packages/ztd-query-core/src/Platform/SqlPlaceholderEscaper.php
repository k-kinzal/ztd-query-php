<?php

declare(strict_types=1);

namespace ZtdQuery\Platform;

interface SqlPlaceholderEscaper
{
    public function escape(string $sql): string;
}

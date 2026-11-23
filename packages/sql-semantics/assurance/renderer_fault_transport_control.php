<?php

declare(strict_types=1);

echo json_encode(['php' => PHP_VERSION], JSON_THROW_ON_ERROR) . "\n";
if (fgets(STDIN) !== false) {
    echo "Malformed worker frame\n";
}

<?php

declare(strict_types=1);

namespace Harbor\Validation;

use Harbor\Http\Problem;

final class Input
{
    public static function text(array $input, string $name, int $minimum, int $maximum): string
    {
        $value = $input[$name] ?? null;
        if (!is_string($value) || mb_strlen(trim($value)) < $minimum || mb_strlen(trim($value)) > $maximum || str_contains($value, "\0")) {
            throw new Problem(422, sprintf('%s must contain %d to %d characters.', $name, $minimum, $maximum));
        }
        return trim($value);
    }

    public static function choice(array $input, string $name, array $choices): string
    {
        $value = self::text($input, $name, 1, 100);
        if (!in_array($value, $choices, true)) {
            throw new Problem(422, 'Invalid ' . $name . '.');
        }
        return $value;
    }

    public static function positive(array $input, string $name): int
    {
        $value = filter_var($input[$name] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) {
            throw new Problem(422, $name . ' must be a positive integer.');
        }
        return $value;
    }

    public static function slug(array $input): string
    {
        $slug = self::text($input, 'slug', 3, 80);
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)) {
            throw new Problem(422, 'The slug must contain lowercase words separated by hyphens.');
        }
        return $slug;
    }
}

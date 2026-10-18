# Configuration

Pass `--config FILE` to read YAML settings. Configuration files are not discovered automatically. Without this option, the command uses defaults.

## Example

Save these settings as `deliver.yaml`:

```yaml
paths: [src, lib]
include: ['**/*.php']
exclude: ['Generated/**']
phpVersion: '8.4'
environment:
  APP_ENV: production
  $_GET:
    mode: preview
format: text
```

```console
vendor/bin/deliver 'App\answer#return' --config=deliver.yaml
```

## Fields

Every field is optional. Unknown keys and values of the wrong type are errors.

| Key | Default | Description |
|-----|---------|-------------|
| `paths` | `[.]` | Nonempty list of search roots, relative to this configuration file. |
| `include` | `['**/*.php']` | Nonempty list of root-relative wildcard patterns. |
| `exclude` | `[]` | Root-relative patterns or directory subtrees to exclude. |
| `phpVersion` | `'8.3'` | Target PHP version. Quote the `major.minor` value. |
| `integerBits` | `64` | Target integer width; currently only `64` is supported. |
| `floatPrecision` | `null` | Target precision, an integer of `-1` or greater. `null` leaves precision-dependent conversions unresolved. |
| `environment` | `{}` | Explicit inputs keyed by variable name, such as `$_GET`, or environment name, such as `APP_ENV`. Values may be scalars, arrays, or null. |
| `standardEvaluators` | `true` | Evaluate supported built-in functions. |
| `format` | `text` | Report format: `text` or `json`. |

If `paths` is omitted, the search root is the configuration file's directory. See [file selection](cli.md#file-selection) for wildcard rules. CLI options override these settings; repeated options replace the configured list.

`environment` supplies values for analysis: `APP_ENV` resolves `getenv('APP_ENV')`, and `$_GET` supplies that variable's contents. These values do not change the command's own environment.

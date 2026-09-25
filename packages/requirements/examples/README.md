# Examples

Each directory is a complete, self-contained project: it has its own
`requirements.yaml`, definitions and source documents, and it runs on its own.
Nothing outside the directory reads these files.

| Directory | Shows |
| --- | --- |
| [yaml](yaml/requirements.yaml) | YAML definitions with a sourced requirement, specifications and an independent decision |
| [markdown](markdown/requirements.yaml) | The same definitions written as readable Markdown cards (experimental) |
| [extensions](extensions/requirements.yaml) | A custom source extension and a custom test runner loaded through `bootstrap` |

Run an example from its directory with the package's command:

```bash
cd examples/yaml
php ../../bin/requirements lint
php ../../bin/requirements format --check
php ../../bin/requirements check
php ../../bin/requirements coverage
```

The `extensions` example also links a test, so `php ../../bin/requirements spec`
executes it through the custom runner.

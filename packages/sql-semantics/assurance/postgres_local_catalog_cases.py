"""Author qualifiers and model their native observations from declared coordinates."""

from postgres_reindex_cases import qualified, quoted

ROLES = ("source", "column", "insert", "update", "delete", "refresh")
POSITIONS = {"first-schema": "fixture", "explicit-second-schema": "decoy"}
DATABASE = "ir_fixture"
FOREIGN = "other"
BASE_PATH = ["fixture", "decoy"]


def case(tag, role, form, position, *, name="items", base=11, copies=1, coordinate=None):
    return {"dialect": "postgres", "release": tag, "role": role, "form": form, "position": position,
            "name": name, "base": base, "copies": copies, "coordinate": coordinate}


def relation(item, schema, refresh=False):
    return qualified(schema, item["name"] + (" rollup" if refresh else ""))


def names(item, schema):
    return relation(item, schema), relation(item, schema, refresh=True)


def start_values(item, schema):
    first = item["base"] + (0 if schema == "fixture" else 20)
    return [first], [first, *([first + 10] * item["copies"])]


def source_sql(item):
    schema = POSITIONS[item["position"]]
    target, snapshot = names(item, schema)
    _, rows = start_values(item, schema)
    key = rows[-1]
    value = key - 8
    form = item["form"]
    catalog = {"verified-current": DATABASE, "unknown-current": DATABASE, "mismatched": FOREIGN}.get(form)
    prefix = quoted(catalog) + "." if catalog is not None else ""
    written = quoted(item["name"]) if form == "local" else target
    role = item["role"]
    if role == "column":
        return f'SELECT {prefix}{written}.id AS answer FROM {written} ORDER BY answer'
    if role == "wildcard":
        return f'SELECT {prefix}{written}.* FROM {written} ORDER BY id'
    if role == "source":
        return f'SELECT id AS answer FROM {prefix}{written} ORDER BY answer'
    if role == "nested":
        return f'SELECT q.answer FROM (SELECT id AS answer FROM {prefix}{written}) AS q'
    if role == "declaration":
        return f'CREATE TABLE {prefix}{relation(item, schema, refresh=True)} (id integer)'
    if role == "refresh":
        return f'REFRESH MATERIALIZED VIEW {prefix}{snapshot}'
    if role == "insert":
        return f'INSERT INTO {prefix}{written} (id) VALUES ({value})'
    if role == "update":
        return f'UPDATE {prefix}{written} SET id={value} WHERE id OPERATOR(pg_catalog.=) {key}'
    if role == "delete":
        return f'DELETE FROM {prefix}{written} WHERE id OPERATOR(pg_catalog.=) {key}'
    raise ValueError(role)


def declarations(item):
    def declaration(schema):
        table, rollup = names(item, schema)
        return [f'CREATE TABLE {table} (id integer)', f'CREATE MATERIALIZED VIEW {rollup} AS SELECT id FROM {table}']
    return [*('CREATE SCHEMA ' + label for label in POSITIONS.values()), *declaration("fixture"), *declaration("decoy")]


def analyzed(item):
    sql = source_sql(item)
    definitions = declarations(item)
    if item["role"] == "declaration":
        definitions = [
            *definitions[:2], sql,
            *definitions[2:],
        ]
    return {"dialect": "postgres", "release": item["release"], "schemaPath": BASE_PATH,
            "declarations": definitions, "sql": sql,
            **({"currentCatalog": DATABASE} if item["form"] != "unknown-current" else {})}


def expected(item, original=False):
    role, schema = item["role"], POSITIONS[item["position"]]
    active, values = start_values(item, schema)
    states = {key: dict(zip(("snapshot", "table"), start_values(item, key))) for key in POSITIONS.values()}
    fail = original and item["form"] == "mismatched"
    if fail:
        return {"status": "catalog-binding", "command": None, "sqlState": "0A000", "notices": [], "rows": None,
                "columns": None, "objects": states}
    columns, rows = [], []
    if role in ("source", "column", "wildcard", "nested"):
        columns = [["id" if role == "wildcard" else "answer", 23]]
        rows = [[str(value)] for value in values]
        command = f"SELECT {len(rows)}"
    elif role == "refresh":
        command = "REFRESH MATERIALIZED VIEW"
        states[schema]["snapshot"] = values
    elif role == "insert":
        command = "INSERT 0 1"
        states[schema]["table"] = sorted([*values, values[-1] - 8])
    elif role == "update":
        command = f"UPDATE {item['copies']}"
        states[schema]["table"] = active + [values[-1] - 8] * item["copies"]
    elif role == "delete":
        command = f"DELETE {item['copies']}"
        states[schema]["table"] = active
    else:
        raise ValueError(role)
    return {"status": "catalog-binding", "command": command, "sqlState": "00000", "notices": [],
            "columns": columns, "rows": rows, "objects": states}

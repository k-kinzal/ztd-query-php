"""Independent PostgreSQL sequence removal and default dependency semantics."""

from postgres_create_sequence_cases import sequence, trial as generator
from postgres_reindex_cases import qualified, quoted

STATUS = "sequence-removal"
DATABASE = "ir_fixture"
LOCAL = "ir_counter_target"
OTHER = "ir_counter_other"
ABSENT = "ir_counter_absent"
PLANS = (("local",), ("temporary",), ("other",), ("local", "local", "other"),
         ("missing-relation",), ("missing-schema",), ("unqualified-missing",),
         ("temporary", "missing-relation", "other", "unqualified-missing"))
MISSING = ("missing-relation", "missing-schema", "unqualified-missing")
EXISTING = ("local", "temporary", "other")
NAMES = {"missing-relation": "vacant_counter", "missing-schema": "none_here", "unqualified-missing": "unbound_counter",
         "temporary-missing": "session_absent"}
TABLE = "consumer"
ROW_HEADING = [["n", 20], ["label", 25]]


def trial(tag, selectors, guard, cascade, active, dependent, *, coordinate=None, quoted_name=False, catalog=False,
          base=11, temp_written=False):
    namespaces = {"local": 'IR. "west"' if quoted_name else LOCAL,
                  "other": 'IR. "east"' if quoted_name else OTHER, "temporary": "pg_temp"}
    name = 'Counter. "棚"' if quoted_name else "ticket"
    return {"release": tag, "selectors": list(selectors), "guard": guard, "cascade": cascade,
            "active": active, "dependent": dependent, "coordinate": coordinate, "name": name,
            "namespaces": namespaces, "catalog": catalog, "base": base, "tempWritten": temp_written,
            "quoted": quoted_name}


def targets(item):
    mapped = {
        **{where: [schema, item["name"], "existing"] for where, schema in item["namespaces"].items()},
        "missing-relation": [item["namespaces"]["local"], NAMES["missing-relation"], "missing-sequence"],
        "missing-schema": [ABSENT, NAMES["missing-schema"], "missing-namespace"],
        "unqualified-missing": [None, NAMES["unqualified-missing"], "missing-sequence"],
        "temporary-missing": ["pg_temp", NAMES["temporary-missing"], "missing-sequence"],
    }
    return [mapped[role] for role in item["selectors"]]


def sql(item, *, canonical=False, change=None):
    if not canonical and "command" in item:
        return item["command"]
    roles = item["selectors"]
    selectors = targets(item)
    if change == "reverse":
        selectors = list(reversed(selectors))
    rendered = []
    for i, (scope, name, _presence) in enumerate(selectors):
        if change == "decoy" and scope == item["namespaces"]["local"]:
            scope = item["namespaces"]["other"]
        if not canonical and roles[i] == "temporary" and not item["tempWritten"]:
            scope = None
        value = qualified(scope, name)
        if item["catalog"] and not canonical and scope not in (None, "pg_temp"):
            value = quoted(DATABASE) + "." + value
        rendered.append(value)
    return ("DROP SEQUENCE " + ("IF EXISTS " if item["guard"] else "") + ", ".join(rendered)
            + (" CASCADE" if item["cascade"] and change != "restrict" else " RESTRICT"))


def analyzed(item):
    names = item["namespaces"]
    declarations = [f"CREATE SCHEMA {quoted(ns)}" for ns in (names["local"], names["other"])]
    declarations += [f"CREATE SEQUENCE {qualified(names[where], item['name'])}" for where in EXISTING]
    declarations.append(f"CREATE TABLE {qualified(names['local'], TABLE)} (n bigint, label text)")
    return {"dialect": "postgres", "release": item["release"], "sql": sql(item), "currentCatalog": DATABASE,
            "schemaPath": [names["local"], names["other"], "public"], "declarations": declarations}


def direct(item):
    return {"dialect": "postgres", "release": item["release"],
            "dropSequence": {"targets": targets(item), "ifExists": item["guard"], "cascade": item["cascade"]}}


def generator_spec(item, role):
    storage, mode, offset = {"local": ("smallint", "permanent", 1), "other": ("bigint", "unlogged", 51),
                            "temporary": ("integer", "temporary", 101)}[role]
    base = item["base"] + offset
    return {**generator(item["release"], storage, 1, "default", mode),
            "start": base, "initial": base + 2, "target": [item["namespaces"][role], item["name"]]}


def missing_message(item, selector):
    schema, name, _presence = targets({**item, "selectors": [selector]})[0]
    return f'schema "{schema}" does not exist' if selector == "missing-schema" else f'sequence "{name}" does not exist'


def notices(item):
    frames = []
    for role in item["selectors"]:
        if role in (*MISSING, "temporary-missing") and item["guard"]:
            frames.append(["NOTICE", "00000", missing_message(item, role) + ", skipping", None, None])
    return frames


def failure(item):
    if item.get("nativeRefusal"):
        return item["nativeRefusal"]
    absent = next((role for role in item["selectors"] if role in MISSING or role == "temporary-missing"), None)
    if absent is not None and not item["guard"]:
        return "3F000" if absent == "missing-schema" else "42P01"
    if item["dependent"] and "local" in item["selectors"] and not item["cascade"]:
        return "2BP01"
    return None


def row_values(item):
    return [[item["base"], "shelf"], [item["base"], "shelf"], [None, None]]


def expected(item):
    scoped = item["namespaces"]
    before = {"sequences": {where: sequence(generator_spec(item, where)) for where in EXISTING},
              "default": [scoped["local"], item["name"]] if item["dependent"] else None,
              "rows": row_values(item), "columns": ROW_HEADING}
    state = failure(item)
    dependent_removed = state is None and item["dependent"] and "local" in item["selectors"]
    selected = set(item["selectors"]) if state is None else set()
    during = {**before, "sequences": {where: value for where, value in before["sequences"].items() if where not in selected},
              "default": None if dependent_removed else before["default"]}
    frames = notices(item)
    if dependent_removed:
        frames.append(["NOTICE", "00000", f"drop cascades to default value for column n of table {TABLE}", None, None])
    if state and not item["guard"]:
        frames = []
    return {"status": STATUS, "sqlState": state or "00000", "command": None if state else "DROP SEQUENCE",
            "transactionAfter": ("failed" if state else "active") if item["active"] else "idle",
            "before": before, "during": None if state else during,
            "settled": before if item["active"] or state else during, "notices": frames}


def fixture_sql(item):
    names = item["namespaces"]
    scopes = ", ".join(quoted(ns) for ns in (names["local"], names["other"], ABSENT))
    commands = [f"DROP SCHEMA IF EXISTS {scopes} CASCADE",
                f"CREATE SCHEMA {quoted(names['local'])}", f"CREATE SCHEMA {quoted(names['other'])}"]
    for role in EXISTING:
        commands.append(creation_sql(generator_spec(item, role)))
    consumer = qualified(names["local"], TABLE)
    reference = "'" + qualified(names["local"], item["name"]).replace("'", "''") + "'"
    default = f" DEFAULT pg_catalog.nextval({reference}::regclass)" if item["dependent"] else ""
    commands.append(f"CREATE TABLE {consumer} (n bigint{default}, label text)")
    commands.append(f"INSERT INTO {consumer} VALUES ({item['base']}, 'shelf'), (NULL, NULL), ({item['base']}, 'shelf')")
    return commands


def creation_sql(value):
    target = qualified(*value["target"])
    prefixes = {"permanent": "", "unlogged": "UNLOGGED ", "temporary": "TEMPORARY "}
    return (f"CREATE {prefixes[value['persistence']]}SEQUENCE {target} AS {value['kind']} "
            f"START {value['start']} RESTART {value['initial']}")

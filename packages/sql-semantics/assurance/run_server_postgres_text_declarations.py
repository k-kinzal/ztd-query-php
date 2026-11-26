"""Compare ordered PostgreSQL declaration binding with native type identities."""

from itertools import product
import json
import random
import sys

from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import OBSERVER, main, observer_request
from run_server_postgres_typed_columns import DIAGNOSTICS
from server_cases import observation
from server_join_cases import case, expected as join_expected, slot
from sqlite_domain import RenderFault

LABEL = "postgres-text-declarations"
CONTRACT = "0ecdb3eed7e3e479112c81c0fc734723632f6d0a3a0f1744877f6a8fe6477da7"
BEFORE = "ir_catalog_before"
QUALIFIED = "ir_catalog_qualified"
SHADOW = "ir_catalog_shadow"
DECLARATIONS = [
    f"CREATE TABLE public.{BEFORE} (id integer, label TEXT)",
    "CREATE TEMP TABLE text (part integer)",
    f"CREATE TABLE public.{QUALIFIED} (id integer, label pg_catalog.text)",
    f"CREATE TEMP TABLE {SHADOW} (id integer, label TEXT)",
]
ROWS = {
    BEFORE: [(1, "before α"), (1, "before α"), (2, None), (3, "")],
    QUALIFIED: [(1, "qualified 値"), (2, "qualified\nline"), (2, "qualified\nline"), (None, None)],
}
CHILDREN = {BEFORE: [(9, "before child")], QUALIFIED: [(19, "qualified child")]}
HEADING = ("id", "label")
TYPE_PROBES = (
    ("before_builtin", f"public.{BEFORE}", "pg_catalog.text"),
    ("qualified_builtin", f"public.{QUALIFIED}", "pg_catalog.text"),
    ("shadow_composite", f"pg_temp.{SHADOW}", "pg_temp.text"),
)


def specifications():
    path, profile = TOOL / "postgres-text-declarations-v2.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(profile) == PROJECTION, "postgres-text-declarations-contract-mismatch")
    return json.loads(path.read_text()), {"postgres": json.loads(profile.read_text())["profiles"]["postgres"]}


def probe_sql():
    pieces = [
        f"(SELECT atttypid = '{typename}'::pg_catalog.regtype FROM pg_catalog.pg_attribute "
        f"WHERE attrelid = '{table_name}'::pg_catalog.regclass AND attname = 'label') AS {name}"
        for name, table_name, typename in TYPE_PROBES
    ]
    return "SELECT " + ", ".join(pieces)


def fixtures():
    fixture = []
    for table_name in ROWS:
        fixture.extend((f"DROP TABLE IF EXISTS public.{table_name}_child", f"DROP TABLE IF EXISTS public.{table_name}"))
    fixture += DECLARATIONS
    for table_name, rows in ROWS.items():
        fixture.append(f"CREATE TABLE public.{table_name}_child () INHERITS (public.{table_name})")
        for name, data in ((table_name, rows), (table_name + "_child", CHILDREN[table_name])):
            fixture.append(f"INSERT INTO public.{name} VALUES " + ",".join(
                "(" + ("NULL" if number is None else str(number)) + ","
                + ("NULL" if text is None else "pg_catalog.convert_from(pg_catalog.decode('"
                   + text.encode().hex() + "','hex'),'UTF8')") + ")" for number, text in data
            ))
    return fixture


def prepare(_, db):
    fixture = fixtures()
    sql = probe_sql()
    return {"fixture": db.request({"fixture": fixture}), "fixtureStatements": fixture,
            "typeSql": sql, "types": observation(db.request({"sql": sql}))}


def check_preparation(_, _environment, preparation):
    same({"status": "fixture"}, preparation["fixture"])
    same(probe_sql(), preparation["typeSql"])
    same(fixtures(), preparation["fixtureStatements"])
    same({"status": "rows", "fields": [[name, "bool"] for name, *_ in TYPE_PROBES], "rows": [[True, True, True]]},
         preparation["types"])


def query(tag, source_name, fields, only=False):
    source = {"schema": "public", "table": source_name, "columns": list(HEADING),
              "only": only, "columnKinds": ["int4", "text"]}
    return case("postgres", tag, source,
                [[slot(0) if field == "id" else ["text-slot", 1, 0], label] for field, label in fields])


def finite(tag, origin, projection, policy):
    table_name = {"before": BEFORE, "qualified": QUALIFIED}[origin]
    columns = [("label", "text_value")]
    if projection == "repeated":
        columns += [("id", "row_number"), ("label", "again")]
    return query(tag, table_name, columns, only=policy == "only")


def finite_ids(_, spec, _tag):
    b = spec["finite"]
    coordinates = set(product(b["table"], b["projection"], b["policy"]))
    require(len(coordinates) == b["identities"], "postgres-text-declarations-finite-size", coordinates)
    return coordinates


def normalized(item):
    source = item["source"]
    fields = []
    for term, label in item["columns"]:
        ordinal = term[1]
        name = source["columns"][ordinal]
        role = "text" if term[0] == "text-slot" else "int4"
        require(term[0] in ("slot", "text-slot") and (len(term) == 2 or term[2] == 0)
                and source.get("columnKinds", ["int4"] * len(source["columns"]))[ordinal] == role
                and {"id": "int4", "label": "text"}[name] == role,
                "postgres-text-declarations-slot-kind", [term, source])
        fields.append((name, label))
    return source["schema"], source["table"], source["only"], tuple(fields)


def finite_id(_, reply, _item):
    _, source, only, fields = normalized(reply)
    return ({BEFORE: "before", QUALIFIED: "qualified"}[source], "single" if len(fields) == 1 else "repeated",
            "only" if only else "include")


def analysis_request(tag, sql, definitions, complete=True):
    return {"dialect": "postgres", "release": tag, "sql": sql, "declarations": definitions, "complete": complete}


def requests(_, tag, spec):
    b = spec["finite"]
    for index, cell in enumerate(product(b["table"], b["projection"], b["policy"])):
        item = finite(tag, *cell)
        yield f"finite/{index}", item, item, None
    direct = f"SELECT label AS text_value FROM public.{BEFORE}"
    same_slot = f"SELECT a.label AS text_value,a.id AS row_number,label AS again FROM public.{BEFORE} a"
    qualified = f"SELECT label AS text_value FROM public.{QUALIFIED}"
    scenarios = (
        (direct, DECLARATIONS[:1], finite(tag, "before", "single", "include")),
        (same_slot, DECLARATIONS, finite(tag, "before", "repeated", "include")),
        (qualified, DECLARATIONS, finite(tag, "qualified", "single", "include")),
    )
    for index, (sql, definitions, item) in enumerate(scenarios):
        yield f"analyze/{index}", analysis_request(tag, sql, definitions), item, None
    for seed in spec["exploration"]["seeds"]:
        rng = random.Random(seed)
        for index in range(spec["exploration"]["casesPerSeed"]):
            table_name = rng.choice(tuple(ROWS))
            fields = [("label", "text"), *((rng.choice(HEADING), rng.choice(("value", 'x " y', "再")))
                      for _ in range(rng.randint(1, 7)))]
            item = query(tag, table_name, fields, only=bool(rng.getrandbits(1)))
            yield f"seed/{seed}/{index}", item, item, None
    for index, rejected in enumerate((
        analysis_request(tag, direct, DECLARATIONS[:1], complete=False),
        analysis_request(tag, f"SELECT label FROM pg_temp.{SHADOW}", DECLARATIONS),
    )):
        yield f"negative/{index}", rejected, None, {
            "exception": DIAGNOSTICS + "InvalidConstruction",
            "message": "The bound column must have the signed 32-bit integer type.",
        }


def data(source):
    name = source["table"]
    return HEADING, ROWS[name] + ([] if source["only"] else CHILDREN[name])


def expected(item):
    return join_expected(item, data)


def check_value(item, reply):
    require(reply["status"] == "constructed" and reply["profile"].startswith(item["release"] + "/"),
            "postgres-text-declarations-positive-rejected", reply)
    require(reply["where"] is None and normalized(reply) == normalized(item), "postgres-text-declarations-slot-differs",
            {"expected": item, "actual": reply})


def check_observation(item, actual, sql):
    prediction = expected(item)
    require(actual["status"] == "rows", "postgres-text-declarations-native-rejection", {"sql": sql, "actual": actual})
    require(prediction == actual, "meaning-mismatch", {"sql": sql, "expected": prediction, "observed": actual})


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "rows", "postgres-text-declarations-fault-native")


def sample_forms(forms, reply, *_):
    _, source, only, fields = normalized(reply)
    forms["table/" + source] += 1
    forms[f"only/{only}"] += 1
    if sum(name == "label" for name, _ in fields) > 1:
        forms["repeated-label-slot"] += 1
    if any(name == "id" for name, _ in fields):
        forms["mixed-kinds"] += 1
    if any('"' in label for _, label in fields):
        forms["quoted-heading"] += 1


def required_forms(*_):
    return {"table/" + BEFORE, "table/" + QUALIFIED, "only/True", "only/False", "repeated-label-slot",
            "mixed-kinds", "quoted-heading"}


def controls():
    item = finite("pg-17.2", "before", "single", "include")
    prediction = expected(item)
    check_observation(item, prediction, "known declaration type and values")
    altered = expected(finite("pg-17.2", "qualified", "single", "include"))
    try:
        check_observation(item, altered, "different physical table with the same field type")
    except Failure as failure:
        require(failure.signal == "meaning-mismatch", "postgres-text-declarations-control-signal", failure.detail)
    else:
        raise Failure("postgres-text-declarations-comparator-accepted")
    return {"healthy": prediction, "wrongTable": altered}


def faults(_, tag):
    return [RenderFault(
        "read-other-text-table", "sql-semantics-postgres/src/Ir/SelectSql.php", "$source->table->quoted()",
        f"($source->table->value === '{BEFORE}' ? '\"{QUALIFIED}\"' : $source->table->quoted())",
        finite(tag, "before", "single", "include"), "meaning-mismatch",
    )]


if __name__ == "__main__":
    main(sys.modules[__name__])

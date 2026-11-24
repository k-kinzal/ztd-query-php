"""Independent ordered PostgreSQL namespace index effects."""

from postgres_create_btree_cases import BITS, conflicts, index_record, sql as btree_sql
from postgres_create_schema_cases import EFFECTIVE, OWNER, QUOTED_OWNER, spelling
from postgres_create_sequence_cases import sequence, sql as sequence_sql, trial as sequence_trial
from postgres_reindex_cases import qualified, quoted
from postgres_schema_sequences_cases import sequence_shape
from postgres_schema_tables_cases import physical_table, table, table_sql
from run_server_postgres_with_projection import scan

STATUS = "schema-index-composition"
LAYOUTS = ("vacant", "prior-index", "prior-view", "prior-table", "prior-sequence")
OWNERS = ("effective", "named")
ORDERS = ("index-first", "index-last")
NS = "ir_index_namespace"
DECOY = "ir_index_decoy"
STORAGE = ("INTEGER", "BIGINT", "pg_catalog.bool")
KINDS = ["int4", "int8", "bool"]
INDEX_ORDERS = tuple(BITS)
PREDICATES = (None, [2, "column"], [2, "IS NOT FALSE"], [0, False], ["AND", [2, "column"], [0, True]])


def trial(tag, owner, layout, order, active, *, width=1, base=11, quoted_name=False, derived=False,
          explicit=False, depths=None, repeat=False, coordinate=None):
    named = (QUOTED_OWNER if quoted_name else OWNER) if owner == "named" else None
    namespace = named if derived else ('Index Batch."棚"' if quoted_name else NS)
    relation = 'Input."棚"' if quoted_name else "ledger"
    columns = ['At "line"', "Amount.税", "Active?"] if quoted_name else ["n", "amount", "enabled"]
    slots = depths if depths is not None else [{"keys": [[0, "DESC NULLS LAST"]], "included": [1, 2],
                                               "unique": True, "nulls": True, "predicate": [2, "column"], "only": False}]
    return {"release": tag, "owner": owner, "layout": layout, "order": order, "active": active, "width": width,
            "base": base, "quoted": quoted_name, "derived": derived, "qualified": explicit, "named": named,
            "namespace": namespace, "relation": relation, "columns": columns, "depths": slots, "repeat": repeat,
            "coordinate": coordinate}


def tables(item, scope=None):
    coordinates = [scope or item["namespace"], item["relation"]]
    primary = table(item["release"], coordinates, [[name, storage, True, None] for name, storage in zip(item["columns"], STORAGE)], False)
    if item["repeat"] and scope is None:
        return [primary, table(item["release"], coordinates, [["ignored", "BIGINT", True, None]], True)]
    return [primary]


def generator(item):
    return {**sequence_trial(item["release"], "bigint", 1, "default", "permanent"),
            "target": [item["namespace"], "tickets"]}


def view(item):
    return {"release": item["release"], "target": [item["namespace"], "report"], "columns": ["measure"],
            "query": scan(None, [[["integer", "2"], "measure"]], source=False), "replace": False,
            "securityBarrier": None, "securityInvoker": None, "checkOption": ""}


def view_sql(item, canonical):
    schema, name = view(item)["target"]
    coordinate = qualified(schema if canonical or item["qualified"] else None, name)
    literal = "CAST(2 AS pg_catalog.int4)" if canonical else "2"
    return f'CREATE VIEW {coordinate} ("measure") AS SELECT {literal} AS "measure"'


def index(item, name, policies, occupied):
    return {"dialect": "postgres", "release": item["release"], "coordinate": None, "observeMembership": True,
            "observeTruthValues": True, "rows": rows(item),
            "createBtree": {"table": [item["namespace"], item["relation"], item["columns"], KINDS, policies["only"]],
                            "name": name, "predicate": policies["predicate"], "keys": policies["keys"],
                            "included": policies["included"], "presence": "occupied" if occupied else "vacant",
                            "ifNotExists": occupied, "unique": policies["unique"], "nullsNotDistinct": policies["nulls"],
                            "concurrently": False}}


def indexes(item):
    lookup = 'Choose index."棚"' if item["quoted"] else "lookup"
    layout = item["layout"]
    chosen = {"vacant": lookup, "prior-index": lookup, "prior-view": "report",
              "prior-table": item["relation"], "prior-sequence": "tickets"}[layout]
    result = []
    if layout == "prior-index":
        prior = {"keys": [[1, "ASC NULLS LAST"]], "included": [], "unique": False,
                 "nulls": False, "predicate": None, "only": False}
        result.append(index(item, lookup, prior, False))
    for ordinal in range(item["width"]):
        name = chosen if ordinal == 0 else f"extra {ordinal}"
        result.append(index(item, name, item["depths"][ordinal % len(item["depths"])], ordinal == 0 and layout != "vacant"))
    return result


def mutated(index, change):
    body = index["createBtree"]
    if change == "key":
        return {**index, "createBtree": {**body, "keys": [[1, body["keys"][0][1]], *body["keys"][1:]]}}
    if change == "nulls":
        return {**index, "createBtree": {**body, "nullsNotDistinct": False}}
    if change == "predicate":
        return {**index, "createBtree": {**body, "predicate": None}}
    return index


def sql(item, *, canonical=False, change=None):
    if not canonical and "command" in item:
        return item["command"]
    header = spelling(item["namespace"] if canonical or not item["derived"] else None,
                      quoted(item["named"]) if item["named"] is not None else "CURRENT_USER" if canonical else None, False)
    parts = [sequence_sql(generator(item), canonical=canonical),
             *(table_sql(value, canonical or item["qualified"], canonical) for value in tables(item)), view_sql(item, canonical)]
    index_parts = [] if change == "phase" else [
        btree_sql(mutated(value, change), unqualified=not (canonical or item["qualified"])) for value in indexes(item)
    ]
    combined = [*index_parts, parts[-1], *parts[:-1]] if not canonical and item["order"] == "index-first" else [*parts, *index_parts]
    return " ".join([header, *combined])


def analyzed(item):
    return {"dialect": "postgres", "release": item["release"], "sql": sql(item), "schemaPath": [DECOY, "public"],
            "declarations": [f"CREATE ROLE {quoted(role)}" for role in (OWNER, QUOTED_OWNER)]
            + [f"CREATE SCHEMA {quoted(DECOY)}", table_sql(tables(item, DECOY)[0], True, False)]}


def direct(item):
    return {"dialect": "postgres", "release": item["release"], "createSchema": {
        "name": None if item["derived"] else item["namespace"], "ifNotExists": False,
        "owner": {"session": "CURRENT_USER"} if item["named"] is None else {"named": item["named"]},
        "tables": tables(item), "sequences": [sequence_shape(generator(item), False)], "views": [view(item)],
        "indexes": [{"release": value["release"], **value["createBtree"]} for value in indexes(item)],
    }}


def rows(item, external=False):
    base = item["base"] + (800 if external else 0)
    return [[base, 7_000_000_000 + base, True], [base + 1, 7_000_000_001 + base, False],
            [base + 2, 7_000_000_002 + base, None], [None, None, True]]


def proposals(item):
    first, off, unknown, null = rows(item)
    return [first, off, unknown, null, [first[0], first[1] + 511, True],
            [first[0] + 512, first[1], True], [first[0], first[1], False]]


def probes(item, acting):
    data = rows(item)
    return ["23505" if any(
        definition["unique"] and conflicts(definition["keys"], [*data, candidate], definition["nullsNotDistinct"], definition["predicate"])
        for definition in acting
    ) else "00000" for candidate in proposals(item)]


def expected(item):
    owner = item["named"] or EFFECTIVE
    baseline = {"owner": None, "table": None, "sequence": None, "view": None, "indexes": {},
                "external": rows(item, True)}
    code = item.get("nativeRefusal", "00000")
    after = None
    notices = []
    checks = None
    if code == "00000":
        after = {**baseline, "owner": owner, "table": physical_table(tables(item)[0], owner),
                 "sequence": {**sequence(generator(item)), "owner": owner},
                 "view": {"owner": owner, "heading": [["measure", 23]], "rows": [[2]],
                          "dependencies": [[item["namespace"], "report", None, 0, "i"]]}, "indexes": {}}
        if item["repeat"]:
            notices.append(["NOTICE", "42P07", f'relation "{item["relation"]}" already exists, skipping', None, None])
        acting = []
        for value in indexes(item):
            shape = value["createBtree"]
            if shape["presence"] == "occupied":
                notices.append(["NOTICE", "42P07", f'relation "{shape["name"]}" already exists, skipping', None, None])
                continue
            after["indexes"][shape["name"]] = {"owner": owner, **index_record(value, True)}
            acting.append(shape)
        checks = probes(item, acting)
    return {"status": STATUS, "sqlState": code, "command": "CREATE SCHEMA" if after is not None else None, "notices": notices,
            "transactionAfter": ("active" if after is not None else "failed") if item["active"] else "idle",
            "before": baseline, "during": after, "settled": baseline if item["active"] or after is None else after,
            "writeProbes": checks}

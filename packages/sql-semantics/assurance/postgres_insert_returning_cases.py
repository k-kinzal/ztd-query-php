"""Direct INSERT values, query sources and native affected-row observations."""

from itertools import product
from random import Random

from postgres_int4_cases import EXCEPTION
from server_compound_cases import leaf, without_profiles
from server_insert_cases import DEFAULTS, case as insertion, declarations, expected as literal_effect, observation as insert_observation
from server_insert_query_cases import DATA, SCAN, expected as query_effect, sql_declarations, table, typed_distinct_output
from server_join_cases import slot
from server_returning_model import fields_from, ordered_returning, project_returned

COLUMNS = tuple(DEFAULTS)
SOURCE_VARIANTS = (None, "ir_insert_scan", "ir_insert_zero")


def insertion_case(tag, written, outputs, *, rows=None, query=None, layout=COLUMNS):
    result = insertion("postgres", tag, list(layout), rows)
    if query is not None:
        del result["rows"]
        result["insertQuery"] = query
    result["written"] = [slot(layout.index(name)) for name in written]
    result["returning"] = fields_from([(layout.index(name), label) for name, label in outputs])
    return result


def select(tag, expressions, source=None, where=None, distinct=False):
    fields = [[expression, f"p{number}"] for number, expression in enumerate(expressions)]
    query = {**leaf("postgres", tag, fields, table("postgres", source), where), "distinct": distinct}
    return typed_distinct_output("postgres", query)


def finite(tag, contract):
    finite = contract["finite"]
    for written, outputs in product(finite["writeLayouts"], finite["outputLayouts"]):
        width = len(written)
        yield insertion_case(tag, written, outputs, rows=[[None, "4"][:width]])
        yield insertion_case(tag, written, outputs, rows=[["2", "-5"][:width]] * 2)
        for source in SOURCE_VARIANTS:
            expressions = [["integer", "4"], ["integer", "-7"]] if source is None else [slot(0), slot(1)]
            yield insertion_case(tag, written, outputs, query=select(tag, expressions[:width], source))


def finite_id(_engine, response, item):
    finite = item["finiteContract"]
    physical = response["target"]["columns"]
    writes = [physical[expression[1]] for expression in response["written"]]
    returns = [[physical[expression[1]], name] for expression, name in response["returning"]]
    if "rows" in response:
        form, shape = 0, len(response["rows"]) - 1
    else:
        source = response["insertQuery"]["source"]
        form, shape = 1, SOURCE_VARIANTS.index(None if source is None else source["table"])
    return form, finite["writeLayouts"].index(writes), shape, finite["outputLayouts"].index(returns)


def finite_ids(_engine, contract, _tag):
    finite = contract["finite"]
    widths = range(len(finite["writeLayouts"]))
    outputs = range(len(finite["outputLayouts"]))
    return {(form, write, variant, output)
            for form, variants in enumerate(("literalVariants", "queryVariants"))
            for write in widths for variant in range(len(finite[variants])) for output in outputs}


def seeded(tag, seed, count):
    rng = Random(seed)
    for _ in range(count):
        written = rng.sample(COLUMNS, rng.randrange(1, 4))
        layout = tuple(rng.sample(COLUMNS, len(COLUMNS)))
        outputs = [(rng.choice(COLUMNS), rng.choice(("same", "same", "current", "列", 'a"b'))) for _ in range(rng.randrange(1, 6))]
        values = ("-2147483648", "-4", "0", "19", "2147483647", None)
        if rng.getrandbits(1):
            rows = [[rng.choice(values) for _ in written] for _ in range(rng.randrange(1, 5))]
            yield insertion_case(tag, written, outputs, rows=rows, layout=layout)
        else:
            source = rng.choice(SOURCE_VARIANTS)
            expressions = [["integer", number] if number is not None else ["null"] for number in values]
            if source is not None:
                expressions += [slot(0), slot(1)]
            query = select(tag, rng.choices(expressions, k=len(written)), source,
                           rng.choice((None, ["boolean", False])), bool(rng.getrandbits(1)))
            yield insertion_case(tag, written, outputs, query=query, layout=layout)


def analyzed(tag, sql, declarations_=None):
    return {"dialect": "postgres", "release": tag, "sql": sql,
            "declarations": declarations_ if declarations_ is not None else sql_declarations("postgres")}


def positives(tag):
    start = "INSERT INTO public.ir_insert_target"
    fields = [("stamp", "kept"), ("id", "kept"), ("delta", "last")]
    yield (analyzed(tag, start + " (delta, id) VALUES (NULL, 4), (NULL, 4) RETURNING stamp AS kept, id AS kept, delta AS last"),
           insertion_case(tag, ["delta", "id"], fields, rows=[[None, "4"], [None, "4"]], layout=("delta", "id", "stamp")))
    fields = [(name, name) for name in COLUMNS]
    yield (analyzed(tag, start + " AS r (delta) VALUES (7) RETURNING r.*"),
           insertion_case(tag, ["delta"], fields, rows=[["7"]], layout=("delta", "id", "stamp")))
    yield (analyzed(tag, start + " (id) SELECT id AS p0 FROM public.ir_insert_scan RETURNING stamp AS kept, id AS kept"),
           insertion_case(tag, ["id"], [("stamp", "kept"), ("id", "kept")],
                          query={**select(tag, [slot(0)], "ir_insert_scan"),
                                 "source": table("postgres", "ir_insert_scan", ("id",))}, layout=("id", "stamp")))
    yield (analyzed(tag, start + " (id) SELECT id AS p0 FROM public.ir_insert_zero RETURNING delta, stamp"),
           insertion_case(tag, ["id"], [("delta", "delta"), ("stamp", "stamp")],
                          query={**select(tag, [slot(0)], "ir_insert_zero"),
                                 "source": table("postgres", "ir_insert_zero", ("id",))}))


def negatives(tag):
    value = insertion_case(tag, ["id"], [("stamp", "kept")], rows=[["1"]])
    for written, message in (
        ([slot(3)], "write slot must belong"),
        ([slot(0, 1)], "write slot must belong"),
        ([slot(0), slot(0)], "cannot repeat a write slot"),
    ):
        yield {**value, "written": written}, EXCEPTION, message
    for field, message in (
        (slot(3), "returned slot must belong"),
        (slot(0, 1), "must read the single target occurrence"),
    ):
        yield {**value, "returning": [[field, "missing"]]}, EXCEPTION, message
    yield {**value, "rows": [["1", "2"]]}, EXCEPTION, "must match the target width"
    invalid = insertion_case(tag, ["id"], [("stamp", "kept")], query=select(tag, [["integer", "1"], ["integer", "2"]]))
    yield invalid, EXCEPTION, "must match the target width"
    start = "INSERT INTO public.ir_insert_target (id) VALUES (1) RETURNING "
    yield (analyzed(tag, start + "missing"), "SqlSemantics\\Platform\\PostgreSql\\Model\\Diagnostic\\SemanticException",
           "Column missing does not exist")
    yield analyzed(tag, start + "id + 1 AS computed"), EXCEPTION, "requires a resolved target column"
    invalid_query = {**select(tag, [["null"]]), "distinct": True}
    yield (insertion_case(tag, ["id"], [("stamp", "kept")], query=invalid_query),
           EXCEPTION, "A DISTINCT insert projection cannot assign an untyped NULL to a non-text column.")


def requests(_engine, tag, contract):
    for index, item in enumerate(finite(tag, contract)):
        yield f"finite/{index}", item, {**item, "finiteContract": contract["finite"]}, None
    settings = contract["exploration"]
    for seed in settings["seeds"]:
        for index, item in enumerate(seeded(tag, seed, settings["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (request, item) in enumerate(positives(tag)):
        yield f"analysis/{index}", request, item, None
    for index, (item, exception, message) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, {"exception": exception, "message": message}


def structure(item):
    layout = {key: item[key] for key in ("target", "written", "returning")}
    if "rows" in item:
        return {**layout, "rows": item["rows"]}
    return {**layout, "insertQuery": without_profiles(item["insertQuery"])}


def expected(item):
    target = item["target"]
    names = [target["columns"][expression[1]] for expression in item["written"]]
    write = {**item, "target": {**target, "columns": names}}
    if "rows" in item:
        measured = literal_effect(write)
        for phase in ("after", "restored"):
            measured[phase].update(scan=SCAN, zero=DATA["ir_insert_zero"])
        measured = insert_observation(measured)
    else:
        measured = query_effect(write)
    return project_returned(item, measured, (event[1:] for event in measured["after"]["events"]), COLUMNS)


def observation(value):
    return ordered_returning(insert_observation(value))

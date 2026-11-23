"""Compare PostgreSQL FULL merge origins, equality conjunctions and extended rows."""

from itertools import product
import json
import random
import sys

from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import OBSERVER, check_preparation, main, observer_request
from run_server_postgres_merged_join import (
    CHILD, DATA as BASE_DATA, DECLARATIONS as BASE_DECLARATIONS, DIAGNOSTICS, EMPTY, LEFT, RIGHT, WIDE,
    HEADINGS as BASE_HEADINGS, fixture as base_fixture,
)
from server_cases import observation
from server_join_cases import compare, expected as join_expected, join, named_binary_graph as graph, slot
from sqlite_domain import RenderFault

LABEL = "postgres-full-merged-join"
CONTRACT = "faf12fc5cf9304be68cc1395a344a157fb5dc20a5f5fe04c18517058455f17ec"
DISJOINT, KEYED_EMPTY = "ir_merge_disjoint", "ir_merge_keyed_empty"
HEADINGS = {**BASE_HEADINGS, DISJOINT: ("other",), KEYED_EMPTY: BASE_HEADINGS[RIGHT]}
DATA = {**BASE_DATA, DISJOINT: [(42,), (None,)], KEYED_EMPTY: []}
DECLARATIONS = [*BASE_DECLARATIONS, f"CREATE TABLE public.{DISJOINT} (other INTEGER)",
                f"CREATE TABLE public.{KEYED_EMPTY} (lane INTEGER, right_value INTEGER, id INTEGER)"]
MASKS = {"one": ("id",), "two": ("id", "lane"), "none": ()}


def specifications():
    path, profile = TOOL / "postgres-full-merged-join-v1.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(profile) == PROJECTION, "postgres-full-merge-contract-mismatch")
    return json.loads(path.read_text()), {"postgres": json.loads(profile.read_text())["profiles"]["postgres"]}


def prepare(_, db):
    result = db.request({"fixture": [*base_fixture(), f"DROP TABLE IF EXISTS public.{DISJOINT}",
                                     f"DROP TABLE IF EXISTS public.{KEYED_EMPTY}", *DECLARATIONS[-2:],
                                     f"INSERT INTO public.{DISJOINT} VALUES (42),(NULL)"]})
    same({"status": "fixture"}, result)
    return result


def reference(inputs, occurrence, name):
    return slot(HEADINGS[inputs[occurrence]].index(name), occurrence)


def physical(inputs, order=(0, 1)):
    return [(occurrence, name, name) for occurrence in order for name in HEADINGS[inputs[occurrence]]]


def visible(inputs, keys):
    return [(None, key, key) for key in keys] + [field for field in physical(inputs) if field[1] not in keys]


def case(tag, inputs, keys, fields, only=(False, False), association=None):
    leaves = [{"schema": "public", "table": name, "columns": list(HEADINGS[name]), "only": own} for name, own in zip(inputs, only)]
    lhs, rhs = leaves
    terms = [compare("=", reference(inputs, 0, key), reference(inputs, 1, key)) for key in keys]
    on = ["boolean", True] if not terms else terms[0]
    if len(terms) == 2:
        on = ["logic", "AND", terms[0], terms[1]]
        if association == "left":
            on = ["logic", "AND", on, terms[0]]
        if association == "right":
            on = ["logic", "AND", terms[0], on]

    def expression(occurrence, name):
        if occurrence is not None:
            return reference(inputs, occurrence, name)
        a, b = reference(inputs, 0, name), reference(inputs, 1, name)
        return ["int4-case", [[["null-test", True, a], a]], b]

    return {"dialect": "postgres", "release": tag, "source": join("FULL", lhs, rhs, on),
            "columns": [[expression(occurrence, name), label] for occurrence, name, label in fields], "where": None}


def finite(tag, mask, orientation, projection, secondary, inheritance):
    keys = MASKS[mask]
    other = (RIGHT if secondary == "populated" else KEYED_EMPTY) if keys else (DISJOINT if secondary == "populated" else EMPTY)
    inputs = (LEFT, other) if orientation == "left-first" else (other, LEFT)
    only = tuple(name == LEFT and inheritance == "only" for name in inputs)
    fields = visible(inputs, keys) if projection == "merged" else physical(inputs, (1, 0))
    return case(tag, inputs, keys, fields, only)


def analyzed(tag):
    left, right, empty, disjoint = (f"public.{name}" for name in (LEFT, RIGHT, EMPTY, DISJOINT))
    inputs = (LEFT, RIGHT)
    first, keys, backwards = MASKS["one"], MASKS["two"], MASKS["two"][::-1]
    yield f"SELECT id AS merged FROM {left} FULL JOIN {right} USING (id)", case(tag, inputs, first, [(None, "id", "merged")])
    yield f"SELECT * FROM {left} FULL JOIN {right} USING (lane,id)", case(tag, inputs, backwards, visible(inputs, backwards))
    yield f"SELECT * FROM {left} NATURAL FULL JOIN {right}", case(tag, inputs, keys, visible(inputs, keys))
    yield f"SELECT a.id AS left_id,b.id AS right_id,m.* FROM {left} a FULL JOIN {right} b USING (lane,id) AS m", case(
        tag, inputs, backwards, [(0, "id", "left_id"), (1, "id", "right_id"), (None, "lane", "lane"), (None, "id", "id")])
    yield f"SELECT a.*,b.* FROM {left} a NATURAL FULL JOIN {right} b", case(tag, inputs, keys, physical(inputs))
    for name, sql in ((EMPTY, empty), (DISJOINT, disjoint)):
        sources = (LEFT, name)
        yield f"SELECT * FROM {left} NATURAL FULL JOIN {sql}", case(tag, sources, (), physical(sources))
    yield f"SELECT * FROM ONLY {left} NATURAL FULL JOIN {right}", case(
        tag, inputs, keys, visible(inputs, keys), (True, False))


def analysis_request(tag, sql):
    return {"dialect": "postgres", "release": tag, "sql": sql, "declarations": DECLARATIONS}


def negatives(tag):
    def witness():
        return finite(tag, "two", "left-first", "merged", "populated", "include")

    on_boundary = {"exception": DIAGNOSTICS + "InvalidConstruction", "message": "requires integer equality between opposite inputs"}
    disjunction = witness()
    disjunction["source"]["on"][1] = "OR"
    yield disjunction, on_boundary
    same_side = witness()
    same_side["source"]["on"][3][3] = slot(1)
    yield same_side, on_boundary
    false = witness()
    false["source"]["on"] = ["boolean", False]
    yield false, on_boundary
    invalid_slot = witness()
    invalid_slot["columns"][0][0][2] = slot(99, 1)
    yield invalid_slot, {"exception": DIAGNOSTICS + "InvalidConstruction", "message": "must belong to the SELECT source"}
    yield analysis_request(tag, f"SELECT 1 FROM public.{LEFT} FULL JOIN public.{WIDE} USING (id)"), {
        "exception": DIAGNOSTICS + "InvalidConstruction", "message": "signed 32-bit integer type"}


def requests(_, tag, spec):
    bounds = spec["finite"]
    for index, coordinates in enumerate(product(*(bounds[key] for key in ("keys", "orientation", "projection", "secondary", "inheritance")))):
        item = finite(tag, *coordinates)
        yield f"finite/{index}", item, item, None
    cases = list(analyzed(tag))
    require(len(cases) == len(spec["analysis"]), "incomplete-postgres-full-merge-analysis")
    for index, (sql, item) in enumerate(cases):
        yield f"analyze/{index}", analysis_request(tag, sql), item, None
    for seed in spec["exploration"]["seeds"]:
        rng = random.Random(seed)
        for index in range(spec["exploration"]["casesPerSeed"]):
            partner = rng.choice((RIGHT, KEYED_EMPTY, EMPTY, DISJOINT))
            inputs = (LEFT, partner)
            if rng.getrandbits(1):
                inputs = inputs[::-1]
            keys = rng.sample(("id", "lane"), rng.randint(1, 2)) if partner in (RIGHT, KEYED_EMPTY) else ()
            candidates = visible(inputs, keys) + physical(inputs)
            fields = [(*field[:2], rng.choice((field[2], "値", 'x " field')))
                      for field in rng.choices(candidates, k=rng.randint(1, 7))]
            item = case(tag, inputs, keys, fields, (bool(rng.getrandbits(1)), bool(rng.getrandbits(1))),
                        rng.choice((None, "left", "right")))
            yield f"seed/{seed}/{index}", item, item, None
    for index, (input_, boundary) in enumerate(negatives(tag)):
        yield f"negative/{index}", input_, None, boundary


def finite_id(_, reply, _item):
    _, sources, on, fields = graph(reply)
    mask = {"boolean": "none", "comparison": "one", "logic": "two"}[on[0]]
    first, second = sources
    direction = "left-first" if first[1] == LEFT else "right-first"
    base, other = (first, second) if direction == "left-first" else (second, first)
    value = fields[0][0]
    projection = "physical-reversed" if value[:2] == ("slot", 1) else "merged"
    return mask, direction, projection, "empty" if other[1] in (EMPTY, KEYED_EMPTY) else "populated", "only" if base[2] else "include"


def finite_ids(_, spec, _tag):
    b = spec["finite"]
    identities = {(k, o, p, s, i) for k in b["keys"] for o in b["orientation"] for p in b["projection"]
                  for s in b["secondary"] for i in b["inheritance"]}
    require(len(identities) == b["identities"], "wrong-postgres-full-merge-domain", len(identities))
    return identities


def heading_and_rows(source):
    name = source["table"]
    children = DATA[CHILD] if name == LEFT and not source["only"] else []
    return HEADINGS[name], DATA[name] + children


def expected(item):
    return join_expected(item, heading_and_rows)


def check_value(item, reply):
    require(reply["status"] == "constructed", "postgres-full-merge-positive-rejected", reply)
    require(reply["profile"].startswith(item["release"] + "/") and reply["where"] is None
            and graph(reply) == graph(item), "postgres-full-merge-provenance-differs", {"expected": item, "actual": reply})


def check_observation(item, actual, sql):
    prediction = expected(item)
    require(actual["status"] == "rows", "postgres-full-merge-native-rejection", {"sql": sql, "actual": actual})
    require(prediction == actual, "meaning-mismatch", {"sql": sql, "expected": prediction, "observed": actual})


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "rows", "postgres-full-merge-fault-native")


def sample_forms(forms, reply, *_):
    _, sources, on, fields = graph(reply)
    forms[f"kind/{on[0]}"] += 1
    forms[f"base-first/{sources[0][1] == LEFT}"] += 1
    for _, name, only in sources:
        forms[f"only/{only}"] += 1
        forms[f"empty/{name in (EMPTY, KEYED_EMPTY)}"] += 1
    if on[0] == "logic":
        forms[f"left-branch/{on[2][0]}"] += 1
        forms[f"right-branch/{on[3][0]}"] += 1
    for term, label in fields:
        forms[f"projection/{term[0]}"] += 1
        if '"' in label:
            forms["quoted-label"] += 1


def required_forms(*_):
    return {"kind/boolean", "kind/comparison", "kind/logic", "base-first/True", "base-first/False",
            "only/True", "only/False", "empty/True", "empty/False", "left-branch/logic", "right-branch/logic",
            "projection/slot", "projection/int4-case", "quoted-label"}


def controls():
    item = finite("pg-17.2", "two", "left-first", "merged", "populated", "include")
    prediction = expected(item)
    check_observation(item, prediction, "known two-origin values")
    altered = {**prediction, "rows": prediction["rows"][1:]}
    try:
        check_observation(item, altered, "lost FULL result row")
    except Failure as failure:
        require(failure.signal == "meaning-mismatch", "wrong-postgres-full-merge-control", failure.detail)
    else:
        raise Failure("postgres-full-merge-comparator-accepted")
    return {"healthy": prediction, "lostRow": altered}


def faults(_, tag):
    target = "sql-semantics-postgres/src/Ir/SelectSql.php"
    normal = finite(tag, "two", "left-first", "merged", "populated", "include")
    return [
        RenderFault("erase-full-else-origin", target, "$this->integerOrNull($expression->otherwise, $source, $offset, $qualify)",
                    "$this->integerOrNull($expression->arms[0]->result, $source, $offset, $qualify)", normal, "meaning-mismatch"),
        RenderFault("drop-full-second-equality", target, "$this->predicate($source->on, $source, $offset)",
                    "$this->predicate($source->on->left, $source, $offset)", normal, "meaning-mismatch"),
        RenderFault("discard-full-right-rows", target, "$source->kind->value . ' JOIN '", "'LEFT' . ' JOIN '", normal, "meaning-mismatch"),
        RenderFault("drop-full-projected-field", target, "foreach ($select->columns as $slot => $column)", "foreach (array_slice($select->columns, 1) as $slot => $column)",
                    normal, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])

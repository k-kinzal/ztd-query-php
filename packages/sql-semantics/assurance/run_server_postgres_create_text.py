"""Replay checked PostgreSQL text declarations and defaults against native catalog effects."""

from itertools import product
import json
from random import Random
import sys

from run import TOOL, Failure, digest, require
from run_server_create_table import (
    OBSERVER, check_fault_witness, check_observation, check_value, observer_request, prepare, sample_forms,
    check_preparation as check_native_preparation, specifications as native_specifications,
)
from run_server_join import main
from server_create_table_cases import case, expected, observation
from server_delete_cases import literal
from sqlite_domain import RenderFault

LABEL = "postgres-create-text"
CONTRACT = "3ace81b37a0399f31072f1a372e9487c08635f2be7bdf109a74d30d5da609e5c"
DIAGNOSTIC = "SqlSemantics\\Platform\\PostgreSql\\Model\\Diagnostic\\InvalidConstruction"
STORAGE = "pg_catalog.text"
TABLE = "public.ir_create_target"
CLOSED_DECLARATIONS = ("CREATE TABLE public.ir_text_declaration_anchor (id INTEGER)",)
FORMS = {
    "absent": (True, None),
    "null": (True, ["null"]),
    "empty": (True, ["text", ""]),
    "quoted": (True, ["text", "it's \\ text"]),
    "multibyte": (True, ["text", "日本語 𐐷\nnext"]),
    "required-text": (False, ["text", "present"]),
    "signed-min": (True, literal(-2147483648)),
    "signed-zero": (False, literal(0)),
    "required-absent": (False, None),
}


def specifications():
    path = TOOL / "postgres-create-text-v2.json"
    require(digest(path) == CONTRACT, "postgres-create-text-contract-mismatch")
    _, profiles = native_specifications()
    return json.loads(path.read_text()), {"postgres": profiles["postgres"]}


def check_preparation(engine, environment, prepared):
    check_native_preparation(engine, environment, prepared)
    require(environment.get("int4TextCastOverrides") == 0, "postgres-create-text-cast-profile", environment)


def finite_case(tag, form, optional, occupied):
    return case("postgres", tag, [["c0", STORAGE, *FORMS[form]]], optional, occupied)


def finite_ids(_, spec, _tag):
    b = spec["finite"]
    points = set(product(b["forms"], b["ifNotExists"], b["priorTarget"]))
    require(len(points) == b["identities"] and set(FORMS) == set(b["forms"]), "postgres-create-text-finite-size")
    return points


def finite_id(_, value, item):
    structure = value["create"]
    [(name, storage, nullable, default)] = structure["columns"]
    require(name == "c0" and storage == STORAGE, "postgres-create-text-finite-column", structure)
    matched = [key for key, form in FORMS.items() if form == (nullable, default)]
    require(len(matched) == 1, "postgres-create-text-finite-role", structure)
    return matched[0], structure["ifNotExists"], item["occupied"]


def analysis(tag, sql, declarations=()):
    return {"dialect": "postgres", "release": tag, "sql": sql, "declarations": list(declarations)}


def analyzed(tag):
    yield (analysis(tag, f"CREATE TABLE {TABLE} (c0 pg_catalog.text DEFAULT 'it''s \\ text')"),
           finite_case(tag, "quoted", False, False))
    yield (analysis(tag, f"CREATE TABLE {TABLE} (c0 text DEFAULT '日本語 𐐷\nnext')", CLOSED_DECLARATIONS),
           finite_case(tag, "multibyte", False, False))
    yield (analysis(tag, f"CREATE TABLE {TABLE} (c1 INTEGER NOT NULL DEFAULT 3, c0 TEXT DEFAULT -2147483648)", CLOSED_DECLARATIONS),
           case("postgres", tag, [["c1", "INTEGER", False, literal(3)], ["c0", STORAGE, True, literal(-2147483648)]]))
    yield (analysis(tag, f"CREATE TABLE IF NOT EXISTS {TABLE} (c0 text NULL DEFAULT NULL)", CLOSED_DECLARATIONS),
           finite_case(tag, "null", True, True))


def negatives(tag):
    yield case("postgres", tag, [["c0", "INTEGER", True, ["text", "a value"]]]), "A text default requires native text storage."
    yield analysis(tag, f"CREATE TABLE {TABLE} (c0 text)", ["CREATE TEMPORARY TABLE text (a INTEGER)"]), (
        "The column requires a supported builtin storage kind.")
    yield analysis(tag, f'CREATE TABLE {TABLE} (c0 pg_catalog.text COLLATE pg_catalog."POSIX")'), (
        "The column requires a resolved stock PostgreSQL collation.")
    yield analysis(tag, f"CREATE TABLE {TABLE} (c0 pg_catalog.text GENERATED ALWAYS AS ('a') STORED)"), (
        "The column attribute is outside the native declaration domain.")


def seeded(tag, seed, count):
    rng = Random(seed)
    for index in range(count):
        width = rng.randint(1, 5)
        fields = [[f"c{slot}", STORAGE, *rng.choice(tuple(FORMS.values()))] for slot in range(width)]
        if width > 1:
            integer_slot = rng.randrange(width)
            fields[integer_slot] = [f"c{integer_slot}", "INTEGER", rng.choice((False, True)), literal(rng.randint(-100, 100))]
        rng.shuffle(fields)
        yield case("postgres", tag, fields, bool(index & 1), bool(index & 2))


def requests(_, tag, spec):
    b = spec["finite"]
    for index, point in enumerate(product(b["forms"], b["ifNotExists"], b["priorTarget"])):
        item = finite_case(tag, *point)
        yield f"finite/{index}", item, item, None
    cases = tuple(analyzed(tag))
    require(len(cases) == len(spec["analysis"]), "postgres-create-text-analysis-incomplete")
    for index, (request, meaning) in enumerate(cases):
        yield f"analysis/{index}", request, meaning, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, message) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, {"exception": DIAGNOSTIC, "message": message}


def required_forms(*_):
    return {"type/pg_catalog.text", "type/INTEGER", "width/single", "width/multiple", "prior/True", "prior/False",
            "optional/True", "optional/False", "nullability/True", "nullability/False", "default/absent", "default/null",
            "default/integer", "default/text"}


def controls():
    item = finite_case("pg-17.2", "multibyte", False, False)
    prediction = expected(item)
    check_observation(item, prediction, "known text declaration")
    wrong = {**prediction, "defaultInsertion": {"status": "inserted", "values": [""]}}
    try:
        check_observation(item, wrong, "replace the native default value")
    except Failure as error:
        require(error.signal == "meaning-mismatch", "postgres-create-text-wrong-control-signal", error.detail)
    else:
        raise Failure("postgres-create-text-comparator-accepted")
    return {"input": item, "prediction": prediction, "wrongDefault": wrong}


def faults(_, tag):
    base = "sql-semantics-postgres/src/Ir/Schema/"
    field = base + "TableColumn.php"
    quoted = finite_case(tag, "quoted", False, False)
    mixed = case("postgres", tag, [["c1", "INTEGER", False, literal(3)], ["c0", STORAGE, True, ["text", "second"]]])
    return [
        RenderFault("erase-declared-text", field, "$this->default->toString()", "(new TextLiteral(''))->toString()", quoted, "meaning-mismatch"),
        RenderFault("make-required-text-nullable", field, "($this->nullable ? ' NULL' : ' NOT NULL')", "' NULL'",
                    finite_case(tag, "required-absent", False, False), "meaning-mismatch"),
        RenderFault("erase-text-default-clause", field, "' DEFAULT ' . $this->default->toString()", "''", quoted, "meaning-mismatch"),
        RenderFault("reverse-mixed-declaration-order", base + "CreateTable.php", "implode(', ', $columns)",
                    "implode(', ', array_reverse($columns))", mixed, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])

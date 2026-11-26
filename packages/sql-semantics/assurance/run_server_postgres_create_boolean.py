"""Compare native PostgreSQL Boolean declaration storage, input conversion and effects."""

from itertools import product
import json
from random import Random
import re
import sys

from run import TOOL, Failure, digest, require
from run_server import same
from run_server_create_table import (
    OBSERVER, check_value, check_observation, check_fault_witness, observer_request, prepare as prepare_create,
    check_preparation as check_create_preparation,
)
from run_server_join import main
from run_server_postgres_create_text import (
    CLOSED_DECLARATIONS, DIAGNOSTIC, STORAGE as TEXT, TABLE, analysis, specifications as native_specifications,
)
from server_create_table_cases import case, expected, observation
from server_delete_cases import literal
from sqlite_domain import RenderFault

LABEL = "postgres-create-boolean"
CONTRACT = "5afd7c6f36188d8dad72f4ede3628804b8b0ca96f30d376d334d74f70aeab95d"
STORAGE = "pg_catalog.bool"
FORMS = {"absent": None, "null": ["null"], "false": ["boolean", False], "true": ["boolean", True]}
SIBLINGS = {
    "single": [],
    "with-signed": [["c1", "INTEGER", False, literal(97)]],
    "with-text": [["c1", TEXT, True, ["text", "true"]]],
}
PREFIX_INPUT = re.compile(r"(?P<padding>[ \t\n\r\f\v]*)(?P<token>[a-zA-Z0-9]+)(?P=padding)")
RAW_LITERAL = re.compile(r"(?P<escape>E)?'(?P<text>[^']*)'")
INVALID = ("o", "00", "\u00a0true")


def prefix_truth():
    interpretations = {}
    for words, value in ((("true", "yes", "on", "1"), True), (("false", "no", "off", "0"), False)):
        for word in words:
            for width in range(1, len(word) + 1):
                interpretations.setdefault(word[:width], set()).add(value)
    return {key: values.pop() for key, values in interpretations.items() if len(values) == 1}


def specifications():
    path = TOOL / "postgres-create-boolean-v2.json"
    require(digest(path) == CONTRACT, "postgres-create-boolean-contract-mismatch")
    spec = json.loads(path.read_text())
    table = spec["inputLanguage"]
    truth = {**dict.fromkeys(table["true"], True), **dict.fromkeys(table["false"], False)}
    same(prefix_truth(), truth)
    _, profiles = native_specifications()
    return spec, profiles


def finite_points(spec):
    b = spec["finite"]
    return product(b["defaults"], b["nullable"], b["ifNotExists"], b["occupied"], b["layout"])


def lexical_points(spec):
    b = spec["inputLanguage"]
    return product((*b["true"], *b["false"]), b["padding"], b["spelling"], b["paths"])


def finite_ids(_, spec, _tag):
    structure = {("structure", *point) for point in finite_points(spec)}
    lexical = {("input", *point) for point in lexical_points(spec)}
    require(len(structure) == spec["finite"]["identities"] and len(lexical) == spec["inputLanguage"]["identities"],
            "postgres-create-boolean-finite-size")
    return structure | lexical


def finite_case(tag, form, nullable=True, optional=False, occupied=False, layout="single"):
    return case("postgres", tag, [*SIBLINGS[layout], ["c0", STORAGE, nullable, FORMS[form]]], optional, occupied)


def finite_id(_, response, item):
    model = response["create"]
    name, storage, nullable, default = model["columns"][-1]
    matched = [key for key, operand in FORMS.items() if operand == default]
    layout = [key for key, fields in SIBLINGS.items() if fields == model["columns"][:-1]]
    require((name, storage) == ("c0", STORAGE) and len(matched) == 1 and len(layout) == 1,
            "postgres-create-boolean-finite-storage", model)
    return "structure", matched[0], nullable, model["ifNotExists"], item["occupied"], layout[0]


def input_query(tag, token, padding, spelling, path):
    alternate = spelling == "uppercase-or-escape-quote"
    quote = "E'" if alternate and token.isdigit() else "'"
    value = quote + padding + (token.upper() if alternate else token) + padding + "'"
    if path == "qualified-builtin-cast":
        value = f"CAST({value} AS pg_catalog.bool)"
    return analysis(tag, f"CREATE TABLE {TABLE} (c0 BOOLEAN DEFAULT {value})")


def analysis_finite_id(engine, response, item, sql):
    prefix = f"CREATE TABLE {TABLE} (c0 BOOLEAN DEFAULT "
    require(sql.startswith(prefix) and sql.endswith(")"), "postgres-create-boolean-input-shape", sql)
    raw = sql[len(prefix):-1]
    path = "implicit-assignment"
    if raw.startswith("CAST("):
        suffix = " AS pg_catalog.bool)"
        require(raw.endswith(suffix), "postgres-create-boolean-input-cast-shape", sql)
        raw = raw[len("CAST("):-len(suffix)]
        path = "qualified-builtin-cast"
    literal_input = RAW_LITERAL.fullmatch(raw)
    require(literal_input is not None, "postgres-create-boolean-input-literal-shape", sql)
    fields = PREFIX_INPUT.fullmatch(literal_input["text"])
    require(fields is not None, "postgres-create-boolean-input-text-shape", sql)
    token = fields["token"]
    lowered = token.lower()
    if literal_input["escape"] or lowered != token:
        require((token.isdigit() and literal_input["escape"]) or
                (literal_input["escape"] is None and token == token.upper()), "postgres-create-boolean-input-alternate", sql)
        spelling = "uppercase-or-escape-quote"
    else:
        spelling = "lowercase"
    truth = prefix_truth()
    require(lowered in truth, "postgres-create-boolean-input-token", lowered)
    form = "true" if truth[lowered] else "false"
    require(finite_id(engine, response, item) == ("structure", form, True, False, False, "single"),
            "postgres-create-boolean-input-resolved-role", [sql, response])
    return "input", lowered, fields["padding"], spelling, path


def boolean_syntax(tag, clause):
    return analysis(tag, f"CREATE TABLE {TABLE} ({clause})")


def analyzed(tag):
    for raw, form, nullable in (
        ("BOOLEAN DEFAULT TRUE", "true", True),
        ("pg_catalog.bool DEFAULT FALSE", "false", True),
        ("BOOLEAN NOT NULL DEFAULT NULL", "null", False),
        ("bool DEFAULT CAST(NULL AS pg_catalog.bool)", "null", True),
        ("BOOLEAN DEFAULT CAST(TRUE AS pg_catalog.bool)", "true", True),
    ):
        query = analysis(tag, f"CREATE TABLE {TABLE} (c0 {raw})", CLOSED_DECLARATIONS)
        yield query, finite_case(tag, form, nullable)
    fields = [["c1", "BIGINT", True, ["wide-integer", "9223372036854775807"]], ["c0", STORAGE, True, FORMS["true"]]]
    yield boolean_syntax(tag, "c1 BIGINT DEFAULT 9223372036854775807, c0 BOOLEAN DEFAULT TRUE"), case("postgres", tag, fields)


def negatives(tag):
    yield case("postgres", tag, [["c0", "INTEGER", True, FORMS["true"]]]), "A Boolean default requires native Boolean storage."
    yield case("postgres", tag, [["c0", STORAGE, True, literal(1)]]), "The Boolean column requires a Boolean or NULL default."
    for token in INVALID:
        yield boolean_syntax(tag, f"c0 BOOLEAN DEFAULT '{token}'"), "The Boolean default contains an invalid input token."
    yield boolean_syntax(tag, "c0 BOOLEAN DEFAULT CAST('true' AS pg_catalog.text)"), "A Boolean default cast requires pg_catalog.bool."
    yield boolean_syntax(tag, "c0 BOOLEAN DEFAULT (NOT TRUE)"), "A Boolean default requires a literal Boolean input."


def seeded(tag, seed, count):
    rng = Random(seed)
    for index in range(count):
        width = rng.randint(1, 6)
        fields = [[f"c{slot}", STORAGE, bool(rng.randrange(2)), rng.choice(tuple(FORMS.values()))] for slot in range(width)]
        if width > 1:
            slot = rng.randrange(1, width)
            kind = rng.choice((TEXT, "SMALLINT", "INTEGER", "BIGINT"))
            default = ["text", "truth"] if kind == TEXT else literal(rng.randint(-17, 17))
            fields[slot] = [f"c{slot}", kind, True, default]
        rng.shuffle(fields)
        yield case("postgres", tag, fields, bool(index & 1), bool(index & 2))


def requests(_, tag, spec):
    for index, point in enumerate(finite_points(spec)):
        item = finite_case(tag, *point)
        yield f"finite/layout/{index}", item, item, None
    truth = prefix_truth()
    for index, point in enumerate(lexical_points(spec)):
        item = finite_case(tag, "true" if truth[point[0]] else "false")
        yield f"finite/input/{index}", input_query(tag, *point), item, None
    cases = tuple(analyzed(tag))
    require(len(cases) == len(spec["analysis"]), "postgres-create-boolean-analysis-incomplete")
    for index, (query, meaning) in enumerate(cases):
        yield f"analysis/{index}", query, meaning, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (item, message) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, {"exception": DIAGNOSTIC, "message": message}


def invalid_native_inputs():
    for token in INVALID:
        yield f"CREATE TABLE {TABLE} (c0 BOOLEAN DEFAULT '{token}')"


def prepare(engine, db):
    return {
        "outside": prepare_create(engine, db),
        "invalid": {sql: db.request({"sql": sql, "occupied": False}) for sql in invalid_native_inputs()},
    }


def check_preparation(engine, environment, prepared):
    check_create_preparation(engine, environment, prepared["outside"])
    rejection = {"status": "22P02", "active": True, "catalog": None, "defaultInsertion": None,
                 "afterRollback": None, "markerAfterRollback": [0]}
    same({sql: rejection for sql in invalid_native_inputs()}, prepared["invalid"])


def required_forms(*_):
    return {"type/pg_catalog.bool", "type/pg_catalog.text", "type/INTEGER", "width/single", "width/multiple",
            "prior/False", "prior/True", "nullable/False", "nullable/True", "default/absent", "default/null",
            "default/True", "default/False"}


def sample_forms(forms, response, _engine, item):
    fields = response["create"]["columns"]
    forms["width/" + ("single" if len(fields) == 1 else "multiple")] += 1
    forms[f"prior/{item['occupied']}"] += 1
    for _, storage, nullable, default in fields:
        forms[f"type/{storage}"] += 1
        forms[f"nullable/{nullable}"] += 1
        if storage == STORAGE:
            key = "absent" if default is None else "null" if default == ["null"] else default[1]
            forms[f"default/{key}"] += 1


def controls():
    item = finite_case("pg-17.2", "true")
    prediction = expected(item)
    check_observation(item, prediction, "native Boolean TRUE")
    altered = {**prediction, "defaultInsertion": {"status": "inserted", "values": [1]}}
    try:
        check_observation(item, altered, "replace TRUE by integer one")
    except Failure as failure:
        require(failure.signal == "meaning-mismatch", "postgres-create-boolean-control-signal", failure.detail)
        detected = failure.signal
    else:
        raise Failure("postgres-create-boolean-comparator-accepted")
    return {"input": item, "prediction": prediction, "integerInsteadOfBoolean": altered, "detection": detected}


def faults(_, tag):
    base = "sql-semantics-postgres/src/Ir/Schema/"
    field = base + "TableColumn.php"
    truth = finite_case(tag, "true")
    mixed = finite_case(tag, "false", layout="with-text")
    required = finite_case(tag, "null", nullable=False)
    operand = "$this->default->value ? ' DEFAULT TRUE' : ' DEFAULT FALSE'"
    return [
        RenderFault("reverse-postgres-boolean-default", field, operand, "' DEFAULT FALSE'", truth, "meaning-mismatch"),
        RenderFault("omit-postgres-boolean-default", field, operand, "''", truth, "meaning-mismatch"),
        RenderFault("discard-postgres-boolean-required-field", field, "($this->nullable ? ' NULL' : ' NOT NULL')", "' NULL'",
                    required, "meaning-mismatch"),
        RenderFault("reverse-postgres-boolean-declaration", base + "CreateTable.php", "implode(', ', $columns)",
                    "implode(', ', array_reverse($columns))", mixed, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])

"""Independent BMP result headings and native alias observations."""

from itertools import product
from random import Random

ALIASES = {
    "ordinary": "value",
    "only-controls": " \t\x7f\n",
    "leading-space": "  value",
    "leading-del": "\x7fvalue",
    "nonbreaking-space": "\u00a0value",
    "ideographic-space": "\u3000value",
    "trailing-space": "value  ",
    "quoted-delimiters": " `x'\\y",
    "ascii-255": "x" * 254 + "y",
    "ascii-256": "x" * 255 + "y",
    "ascii-257": "x" * 256 + "y",
    "two-byte-256": "æ" * 127 + "ø",
    "two-byte-over": "æ" * 128 + "ø",
    "three-byte-255": "漢" * 84 + "語",
    "three-byte-over": "漢" * 85 + "語",
    "mixed-prefix-255": "x" * 253 + "æ",
    "mixed-prefix-256": "漢" * 85 + "x",
    "mixed-prefix-over": "x" * 255 + "漢",
}
ATOMS = ("1", "-00001", "2147483648", "-9223372036854775808")
STYLES = ("quoted-identifier", "single-quoted-string")
NONGRAPHIC = "".join(map(chr, range(33))) + chr(127)


def effective(requested):
    raw = requested.lstrip(NONGRAPHIC).encode("utf-8")
    return raw[:256].decode("utf-8", errors="ignore")


def wire_expression(token):
    value = int(token)
    padding = len(token.lstrip("-")) - len(str(abs(value)))
    if not -(2**31) <= value <= 2**31 - 1:
        return ["projected-integer", token]
    return ["integer", str(value), padding, False] if padding else ["integer", str(value)]


def wire_label(requested, observed):
    label = effective(requested)
    if label == requested:
        return label
    return ["requested-result-alias", requested, label] if observed else ["requested-result-alias", requested]


def quote(requested, style):
    if style == "quoted-identifier":
        return "`" + requested.replace("`", "``") + "`"
    if style == "single-quoted-string":
        return "'" + requested.replace("\\", "\\\\").replace("'", "''") + "'"
    raise ValueError(style)


def command(item):
    return "SELECT " + ", ".join(token + " AS " + quote(requested, style)
                                for token, requested, style in item["columns"])


def allocated(item, observed=True):
    return [[wire_expression(token), wire_label(alias, observed)] for token, alias, _ in item["columns"]]


def direct(item):
    return {"dialect": "mysql", "release": item["tag"], "columns": allocated(item, observed=False), "source": None,
            "orderBy": [], "limit": None}


def case(tag, *columns):
    return {"tag": tag, "columns": list(columns)}


def grid(contract, analyze):
    finite = contract["finite"]
    styles = finite["analysisSpellings"] if analyze else ("quoted-identifier",)
    yield from product(finite["atoms"], finite["aliasCases"], styles)


def independent_ids(analyze):
    styles = STYLES if analyze else ("quoted-identifier",)
    return {(token, alias, style) for alias in ALIASES for style in styles for token in ATOMS}


def physical(response, style):
    [[operand, label]] = response["columns"]
    if operand[0] == "projected-integer":
        token = operand[1]
    else:
        number, *attribute = operand[1:]
        padding = attribute[0] if attribute else 0
        token = ("-" if number.startswith("-") else "") + "0" * padding + number.lstrip("-")
    requested = label[1] if isinstance(label, list) else label
    matches = [name for name, value in ALIASES.items() if value == requested]
    if len(matches) != 1:
        raise ValueError(requested)
    return token, matches[0], style


def expected(item):
    tag = item["tag"]
    reserve_sign = tag not in ("mysql-5.6.51", "mysql-5.7.44")
    ordered, row, warnings = [], [], []
    for token, requested, _ in item["columns"]:
        native = effective(requested)
        width = len(token.lstrip("-")) + (reserve_sign or token.startswith("-"))
        ordered.append([native.encode("utf-8").hex(), "LONGLONG", ["not_null"], width])
        row.append(int(token))
        if native != requested:
            code = 1466 if native else 1474
            message = f"Leading spaces are removed from name '{requested}'" if native else "Name '" + requested + "' has become ''"
            warnings.append(["Warning", code, message])
    result = {"status": "rows", "fields": ordered, "rows": [row]}
    return {
        "native-prepared": {**result, "prepareWarnings": warnings,
                            "executeWarnings": warnings if tag == "mysql-5.6.51" else []},
        "text": {**result, "prepareWarnings": [], "executeWarnings": warnings},
    }


def seeded(tag, seed, count):
    rng = Random(seed)
    labels = tuple(ALIASES.values())
    for index in range(count):
        width = 1 + index % 4
        repeated = rng.choice((" value", "\tseries", "q" * 257))
        columns = []
        for position in range(width):
            alias = repeated if index % 3 == 1 else rng.choice(labels)
            if index % 3 == 2:
                char = rng.choice(("z", "ø", "語"))
                alias = rng.choice(("", " ", "\t\x7f")) + char * rng.randint(78, min(288, 280 // len(char.encode())))
            columns.append((rng.choice(ATOMS), alias, STYLES[(index + position) % 2]))
        yield case(tag, *columns)

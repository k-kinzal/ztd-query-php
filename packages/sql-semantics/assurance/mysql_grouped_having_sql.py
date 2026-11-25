"""Render independent inputs for the fixed MySQL HAVING finite coordinates."""

from copy import deepcopy


def operand(term, columns):
    kind, *data = term
    if kind == "slot":
        return quote(columns[data[0]])
    if kind in ("integer", "range-integer"):
        return data[0]
    if kind == "null":
        return "NULL"
    if kind == "aggregate":
        function, argument, distinct = data
        source = "*" if argument is None else operand(argument, columns)
        return f"{function.upper()}({'DISTINCT ' if distinct else ''}{source})"
    raise ValueError(term)


def quote(identifier):
    return "`" + identifier.replace("`", "``") + "`"


def command(item):
    grouped = item["grouped"]
    heading = grouped["source"]
    columns = heading["columns"]
    output = ", ".join(f"{operand(term, columns)} AS {quote(label)}" for term, label in grouped["columns"])
    source = f"{quote(heading['schema'])}.{quote(heading['table'])}"
    sql = f"SELECT {output} FROM {source}"
    if grouped["keys"]:
        sql += " GROUP BY " + ", ".join(operand(key, columns) for key in grouped["keys"])
    _, operator, left, right = grouped["having"]
    return f"{sql} HAVING ({operand(left, columns)} {operator} {operand(right, columns)})"


def analyzed_shape(item):
    """Predict occurrence allocation from the fixed query's used fields."""
    expected = deepcopy(item)
    grouped = expected["grouped"]
    source = grouped["source"]
    original = source["columns"]

    def used(term):
        if term[0] == "slot":
            return [term[1]]
        if term[0] == "aggregate" and term[2] is not None:
            return used(term[2])
        return []

    positions = dict.fromkeys(position
                             for term in (*grouped["keys"], *(term for term, _ in grouped["columns"]),
                                          *grouped["having"][2:])
                             for position in used(term))
    mapping = {position: slot for slot, position in enumerate(positions)}

    def renumber(term):
        if term[0] == "slot":
            return ["slot", mapping[term[1]]]
        if term[0] == "aggregate" and term[2] is not None:
            return [term[0], term[1], renumber(term[2]), term[3]]
        return term

    source["columns"] = [original[position] for position in positions]
    source.pop("columnTypes", None)
    grouped["keys"] = [renumber(term) for term in grouped["keys"]]
    grouped["columns"] = [[renumber(term), name] for term, name in grouped["columns"]]
    prefix, operator, left, right = grouped["having"]
    grouped["having"] = [prefix, operator, renumber(left), renumber(right)]
    return expected

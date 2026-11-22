"""Independent tokenization and position-set evaluation of built-in patterns."""

from sqlite_cast_semantics import text_value

NO_ESCAPE = object()
ASCII_FOLD = str.maketrans("ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")


class PatternEvaluationError(ValueError):
    """One specified SQLite pattern evaluation outcome."""


def terminated(text):
    return text.split("\0", 1)[0]


def character_set(pattern, start):
    inverted = pattern[start:start + 1] == "^"
    start += int(inverted)
    first = start + (pattern[start:start + 1] == "]")
    end = pattern.find("]", first)
    if end == -1:
        return None, len(pattern)
    literals = {(ord("]"), ord("]"))} if first != start else set()
    prior = None
    position = first
    while position < end:
        character = ord(pattern[position])
        if character == ord("-") and prior is not None and position + 1 < end:
            position += 1
            literals.add((prior, ord(pattern[position])))
            prior = None
        else:
            literals.add((character, character))
            prior = character
        position += 1
    return (inverted, literals), end + 1


def tokens(pattern, kind, escape):
    position = 0
    many, one = ("%", "_") if kind == "LIKE" else ("*", "?")
    while position < len(pattern):
        character = pattern[position]
        position += 1
        if character == escape:
            if position == len(pattern):
                yield "impossible", None
                return
            yield "literal", pattern[position]
            position += 1
        elif character == many:
            yield "many", None
        elif character == one:
            yield "one", None
        elif kind == "GLOB" and character == "[":
            character_class, position = character_set(pattern, position)
            if character_class is None:
                yield "impossible", None
                return
            yield "set", character_class
        else:
            yield "literal", character


def matched(text, pattern, kind, escape):
    if kind == "LIKE":
        text = text.translate(ASCII_FOLD)
    frontier = {0}
    for token, value in tokens(pattern, kind, escape):
        if not frontier or token == "impossible":
            return False
        if token == "many":
            frontier = set(range(min(frontier), len(text) + 1))
        elif token == "one":
            frontier = {offset + 1 for offset in frontier if offset < len(text)}
        elif token == "set":
            invert, intervals = value
            frontier = {offset + 1 for offset in frontier if offset < len(text)
                        and any(first <= ord(text[offset]) <= last for first, last in intervals) != invert}
        else:
            same = value.translate(ASCII_FOLD) if kind == "LIKE" else value
            frontier = {offset + 1 for offset in frontier if text[offset:offset + 1] == same}
    return len(text) in frontier


def evaluate_pattern(kind, subject, pattern, negated, escape=NO_ESCAPE):
    assert kind in ("LIKE", "GLOB")
    raw_pattern = "" if pattern is None else text_value(pattern)
    if len(raw_pattern.encode(errors="surrogateescape")) > 50000:
        raise PatternEvaluationError("LIKE or GLOB pattern too complex")
    if escape is not NO_ESCAPE:
        assert kind == "LIKE"
        if escape is None:
            return None
        delimiter = terminated(text_value(escape))
        if len(delimiter) != 1:
            raise PatternEvaluationError("ESCAPE expression must be a single character")
    else:
        delimiter = None
    if subject is None or pattern is None:
        return None
    test = matched(terminated(text_value(subject)), terminated(raw_pattern), kind, delimiter)
    return int(test != negated)

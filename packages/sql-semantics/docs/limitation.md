# Limitations

- **No nullability refinement from filters.** `SELECT value FROM t WHERE value
  IS NOT NULL` can still report `MaybeNull` for a nullable `value` column. The
  WHERE condition is not used to narrow the output's NULL facts.
- **Statement sequences do not update the binding context.** `bindAll()` does
  not apply a preceding CREATE TABLE, ALTER TABLE, or SET to subsequent
  statements. For example, creating a table earlier in a script does not make
  it available to a later SELECT in the same binding call.
- **Structural editing is limited to expression replacement.** There is no
  validated editing API for adding or removing a result column, changing a
  JOIN, or rearranging statements.
- **Manually constructed structures cannot be serialized as new SQL.** String
  output uses the retained source tree. Changing semantic objects around an
  existing source does not change that SQL. SQL generation from structures
  constructed from scratch is not provided.

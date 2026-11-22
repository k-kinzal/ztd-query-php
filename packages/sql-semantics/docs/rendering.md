# SQL representation

Call `toString()` on a complete IR. The dialect emitter uses the checked fields to write SQL, including operand grouping, identifiers, result labels and binding markers. It preserves references to the named external objects described by the value. Use the same dialect, language settings and external objects when interpreting the emitted statement.

The projection emitter can insert explicit result aliases to preserve column names. Comparison evidence observes ordered labels, binding information, typed rows, NULL values and duplicate rows. Where the value describes a write, declaration or transaction, it also observes the relevant state changes under the fixed fixture conditions.

See [the checked roles and profiles](semantics-ir.md) and [the evidence guide](../assurance/README.md).

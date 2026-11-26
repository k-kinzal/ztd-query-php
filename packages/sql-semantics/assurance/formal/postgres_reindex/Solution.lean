import ReindexSpec

open ReindexSpec

namespace PostgreSqlReindex

private theorem appendAssoc (first second third : Text) :
    (first ++ second) ++ third = first ++ (second ++ third) := by
  induction first with
  | nil => rfl
  | cons c rest induction => exact congrArg (c :: ·) induction

private theorem scanRoundTrip (name suffix : Text) (boundary : suffix.head? ≠ some quote) :
    scan (escaped name ++ quote :: suffix) = some (name, suffix) := by
  induction name with
  | nil =>
    cases suffix with
    | nil => rfl
    | cons marker rest =>
      have distinct : marker ≠ quote := by
        intro equal
        subst marker
        exact boundary rfl
      change scan (quote :: marker :: rest) = _
      rw [scan.eq_def]
      change (if marker = quote then _ else some ([], marker :: rest)) = _
      rw [ite_eq_right distinct]
  | cons first rest induction =>
    by_cases special : first = quote
    · subst first
      rw [escaped, ite_eq_left rfl]
      change scan (quote :: quote :: (escaped rest ++ quote :: suffix)) = _
      rw [scan.eq_def]
      change addChar quote (scan (escaped rest ++ quote :: suffix)) = _
      rw [induction]
      rfl
    · rw [escaped, ite_eq_right special]
      change scan (first :: (escaped rest ++ quote :: suffix)) = _
      rw [scan.eq_def]
      change (if first = quote then _ else addChar first (scan (escaped rest ++ quote :: suffix))) = _
      rw [ite_eq_right special, induction]
      rfl

theorem quotedIdentifier (name suffix : Text) (boundary : suffix.head? ≠ some quote) :
    getName (putName name ++ suffix) = some (name, suffix) := by
  change getName (quote :: ((escaped name ++ [quote]) ++ suffix)) = _
  rw [appendAssoc, getName, ite_eq_left rfl]
  exact scanRoundTrip name suffix boundary

private theorem cutExact (token rest : Text) :
    cut token (token ++ rest) = some rest := by
  induction token with
  | nil => rfl
  | cons first tail induction =>
    change cut (first :: tail) (first :: (tail ++ rest)) = _
    rw [cut, ite_eq_left rfl, induction]

private theorem cutIncompatible (selector candidate suffix : Text)
    (clash : incompatible selector candidate = true) : cut selector (candidate ++ suffix) = none := by
  induction selector generalizing candidate with
  | nil => cases clash
  | cons first rest induction =>
    cases candidate with
    | nil => cases clash
    | cons x xs =>
      change cut (first :: rest) (x :: (xs ++ suffix)) = _
      by_cases equal : first = x
      · rw [incompatible.eq_def] at clash
        change (if first = x then incompatible rest xs else true) = true at clash
        rw [ite_eq_left equal] at clash
        rw [cut, ite_eq_left equal]
        exact induction xs clash
      · rw [cut, ite_eq_right equal]

private theorem memberChoices (head : Head) : head ∈ choices := by
  cases head with
  | index c v => cases c <;> cases v <;> repeat (first | apply List.Mem.head | apply List.Mem.tail)
  | table c v => cases c <;> cases v <;> repeat (first | apply List.Mem.head | apply List.Mem.tail)
  | schema c v => cases c <;> cases v <;> repeat (first | apply List.Mem.head | apply List.Mem.tail)
  | database c v => cases c <;> cases v <;> repeat (first | apply List.Mem.head | apply List.Mem.tail)
  | system v => cases v <;> repeat (first | apply List.Mem.head | apply List.Mem.tail)

private theorem checkedHeaders :
    (choices.all fun a => choices.all fun b => decide (a = b) || incompatible (opening a) (opening b)) = true :=
  rfl

private theorem andOperands (a b : Bool) (pair : (a && b) = true) : a = true ∧ b = true := by
  cases a <;> cases b
  all_goals first | exact ⟨rfl, rfl⟩ | cases pair

private theorem listAllMember {α : Type} (f : α → Bool) (xs : List α) (a : α)
    (member : a ∈ xs) (observed : xs.all f = true) : f a = true := by
  induction member with
  | head rest => exact (andOperands (f a) (rest.all f) observed).1
  | @tail first rest _ ih => exact ih (andOperands (f first) (rest.all f) observed).2

private theorem decidesFalse {p : Prop} [value : Decidable p] (refutes : ¬p) :
    decide p = false := by
  cases value with
  | isFalse rejected => rfl
  | isTrue selected => exact False.elim (refutes selected)

private theorem headersDistinct (a b : Head) (different : a ≠ b) :
    incompatible (opening a) (opening b) = true := by
  let clashes (x y : Head) := decide (x = y) || incompatible (opening x) (opening y)
  have candidates := listAllMember (fun x => choices.all (clashes x)) choices a (memberChoices a) checkedHeaders
  have pair := listAllMember (clashes a) choices b (memberChoices b) candidates
  change (decide (a = b) || incompatible (opening a) (opening b)) = true at pair
  rw [decidesFalse different] at pair
  exact pair

private theorem mapSame {α β : Type} (entries : List α) (f g : α → Option β)
    (step : ∀ entry, f entry = g entry) : entries.filterMap f = entries.filterMap g := by
  induction entries with
  | nil => rfl
  | cons first rest induction => rw [List.filterMap_cons, List.filterMap_cons, step first, induction]

private theorem takeOne (head : Head) (rest : Text) :
    consumeHead (opening head ++ rest) = [(head, rest)] := by
  have contribution (entry : Head) :
      (cut (opening entry) (opening head ++ rest)).map (entry, ·) =
        if entry = head then some (head, rest) else none := by
    by_cases equal : entry = head
    · subst entry
      rw [cutExact, ite_eq_left rfl]
      rfl
    · rw [cutIncompatible _ _ _ (headersDistinct entry head equal), ite_eq_right equal]
      rfl
  unfold consumeHead
  rw [mapSame _ _ _ contribution]
  cases head with
  | index c v => cases c <;> cases v <;> rfl
  | table c v => cases c <;> cases v <;> rfl
  | schema c v => cases c <;> cases v <;> rfl
  | database c v => cases c <;> cases v <;> rfl
  | system v => cases v <;> rfl

private theorem emptyBoundary : ([] : Text).head? ≠ some quote :=
  of_decide_eq_true rfl

theorem commandRoundTrip (head : Head) (target : Target) (shape : fits head target) :
    identify (encode head target) = some (head, target) := by
  cases target with
  | one name =>
    have arity : head.binary = false := shape
    have layout : encode head (.one name) = opening head ++ (escaped name ++ [quote]) := by
      change header head ++ quote :: (escaped name ++ [quote]) = _
      rw [opening, appendAssoc]
      rfl
    rw [layout, identify, takeOne]
    have value : payload head (escaped name ++ [quote]) = some (.one name) := by
      change (scan (escaped name ++ quote :: []) >>= _) = _
      rw [scanRoundTrip name [] emptyBoundary]
      change (if head.binary then _ else _) = _
      rw [arity]
      rfl
    change (payload head (escaped name ++ [quote])).map (head, ·) = _
    rw [value]
    rfl
  | two schema name =>
    have arity : head.binary = true := shape
    have layout : encode head (.two schema name) =
        opening head ++ (escaped schema ++ quote :: '.' :: putName name) := by
      change header head ++ quote :: ((escaped schema ++ [quote]) ++ '.' :: putName name) = _
      rw [opening, appendAssoc, appendAssoc]
      rfl
    have firstBoundary : ('.' :: putName name).head? ≠ some quote := by
      show some '.' ≠ some quote
      exact of_decide_eq_true rfl
    have secondName : getName (putName name) = some (name, []) := by
      have full := quotedIdentifier name [] emptyBoundary
      -- Avoid importing a list simplification lemma into the proof's dependencies.
      have trim (word : Text) : word ++ [] = word := by
        induction word with
        | nil => rfl
        | cons c rest ih => exact congrArg (c :: ·) ih
      rw [trim] at full
      exact full
    rw [layout, identify, takeOne]
    have value : payload head (escaped schema ++ quote :: '.' :: putName name) = some (.two schema name) := by
      change (scan (escaped schema ++ quote :: '.' :: putName name) >>= _) = _
      rw [scanRoundTrip schema ('.' :: putName name) firstBoundary]
      change (if head.binary then (getName (putName name) >>= closedName).map (Target.two schema) else _) = _
      rw [arity, secondName]
      rfl
    change (payload head (escaped schema ++ quote :: '.' :: putName name)).map (head, ·) = _
    rw [value]
    rfl

end PostgreSqlReindex

#print axioms PostgreSqlReindex.quotedIdentifier
#print axioms PostgreSqlReindex.commandRoundTrip

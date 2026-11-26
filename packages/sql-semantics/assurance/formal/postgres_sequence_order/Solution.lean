import SignedDecimalSpec

namespace SignedDecimal

theorem widthBound (digits : List Digit) : value digits < 10 ^ digits.length := by
  induction digits with
  | nil => exact Nat.zero_lt_one
  | cons head tail ih =>
    have digitBound : head.val + 1 ≤ 10 := head.isLt
    calc
      value (head :: tail) = head.val * 10 ^ tail.length + value tail := rfl
      _ < (head.val + 1) * 10 ^ tail.length := by
        rw [Nat.add_mul, Nat.one_mul]
        exact Nat.add_lt_add_left ih _
      _ ≤ 10 * 10 ^ tail.length := Nat.mul_le_mul_right _ digitBound
      _ = 10 ^ (head :: tail).length := by
        rw [List.length_cons, Nat.pow_succ, Nat.mul_comm]

theorem headBefore (left right : Digit) (tailsLeft tailsRight : List Digit)
    (width : tailsLeft.length = tailsRight.length) (headOrder : left.val < right.val) :
    value (left :: tailsLeft) < value (right :: tailsRight) := by
  calc
    value (left :: tailsLeft) = left.val * 10 ^ tailsLeft.length + value tailsLeft := rfl
    _ < (left.val + 1) * 10 ^ tailsLeft.length := by
      rw [Nat.add_mul, Nat.one_mul]
      exact Nat.add_lt_add_left (widthBound tailsLeft) _
    _ ≤ right.val * 10 ^ tailsLeft.length := Nat.mul_le_mul_right _ headOrder
    _ ≤ value (right :: tailsRight) := by
      rw [width]
      exact Nat.le_add_right _ _

theorem lexOrder (left right : List Digit) (width : left.length = right.length) :
    lexBefore left right ↔ value left < value right := by
  induction left generalizing right with
  | nil =>
    cases right with
    | nil => exact ⟨False.elim, Nat.not_lt_zero 0⟩
    | cons head tail => nomatch width
  | cons leftHead leftTail ih =>
    cases right with
    | nil => nomatch width
    | cons rightHead rightTail =>
      have equalTail : leftTail.length = rightTail.length := Nat.succ.inj width
      constructor
      · rintro (first | ⟨equalHead, rest⟩)
        · exact headBefore leftHead rightHead leftTail rightTail equalTail first
        · change leftHead.val * 10 ^ leftTail.length + value leftTail <
                  rightHead.val * 10 ^ rightTail.length + value rightTail
          rw [equalHead, equalTail]
          exact Nat.add_lt_add_left ((ih rightTail equalTail).mp rest) _
      · intro order
        rcases Nat.lt_trichotomy leftHead.val rightHead.val with first | equalHead | reversed
        · exact Or.inl first
        · refine Or.inr ⟨equalHead, (ih rightTail equalTail).mpr ?_⟩
          change leftHead.val * 10 ^ leftTail.length + value leftTail <
                 rightHead.val * 10 ^ rightTail.length + value rightTail at order
          rw [equalHead, equalTail] at order
          exact Nat.lt_of_add_lt_add_left order
        · exact (Nat.lt_asymm order (headBefore rightHead leftHead rightTail leftTail equalTail.symm reversed)).elim

theorem widthBefore (left right : List Digit) (leftCanonical : Canonical left)
    (rightCanonical : Canonical right) (width : left.length < right.length) :
    value left < value right := by
  cases rightCanonical with
  | zero =>
    cases leftCanonical <;> simp at width
  | positive leading tail nonzero =>
    have rightLonger : left.length ≤ tail.length := by
      simpa only [List.length_cons, Nat.lt_succ_iff] using width
    calc
      value left < 10 ^ left.length := widthBound left
      _ ≤ 10 ^ tail.length := Nat.pow_le_pow_right (by decide) rightLonger
      _ ≤ leading.val * 10 ^ tail.length := by
        simpa only [Nat.one_mul] using Nat.mul_le_mul_right (10 ^ tail.length) nonzero
      _ ≤ value (leading :: tail) := Nat.le_add_right _ _

theorem magnitudeOrder (left right : List Digit) (leftCanonical : Canonical left)
    (rightCanonical : Canonical right) :
    magnitudeBefore left right ↔ value left < value right := by
  constructor
  · rintro (shorter | ⟨same, order⟩)
    · exact widthBefore left right leftCanonical rightCanonical shorter
    · exact (lexOrder left right same).mp order
  · intro order
    rcases Nat.lt_trichotomy left.length right.length with shorter | same | longer
    · exact Or.inl shorter
    · exact Or.inr ⟨same, (lexOrder left right same).mpr order⟩
    · exact (Nat.lt_asymm order (widthBefore right left rightCanonical leftCanonical longer)).elim

theorem decimalOrder
    (leftNegative rightNegative : Bool) (left right : List Digit)
    (leftCanonical : Canonical left) (rightCanonical : Canonical right)
    (leftSign : SignCoherent leftNegative left) (rightSign : SignCoherent rightNegative right) :
    before leftNegative rightNegative left right ↔ signed leftNegative left < signed rightNegative right := by
  cases leftNegative <;> cases rightNegative
  · change magnitudeBefore left right ↔ (value left : Int) < (value right : Int)
    exact (magnitudeOrder left right leftCanonical rightCanonical).trans Int.ofNat_lt.symm
  · change False ↔ (value left : Int) < -(value right : Int)
    have positive : (0 : Int) < (value right : Int) := Int.ofNat_lt.mpr (rightSign rfl)
    have below : -(value right : Int) < 0 := by
      rw [← Int.neg_zero]
      exact Int.neg_lt_neg positive
    have opposite : -(value right : Int) < (value left : Int) :=
      Int.lt_of_lt_of_le below (Int.natCast_nonneg _)
    exact ⟨False.elim, Int.lt_asymm opposite⟩
  · change True ↔ -(value left : Int) < (value right : Int)
    have positive : (0 : Int) < (value left : Int) := Int.ofNat_lt.mpr (leftSign rfl)
    have below : -(value left : Int) < 0 := by
      rw [← Int.neg_zero]
      exact Int.neg_lt_neg positive
    exact ⟨fun _ => Int.lt_of_lt_of_le below (Int.natCast_nonneg _), fun _ => True.intro⟩
  · change magnitudeBefore right left ↔ -(value left : Int) < -(value right : Int)
    exact (magnitudeOrder right left rightCanonical leftCanonical).trans
      (Int.ofNat_lt.symm.trans Int.neg_lt_neg_iff.symm)

theorem representativeDomain :
    Canonical [0] ∧ Canonical [1] ∧ SignCoherent true [1] ∧
      before true false [1] [0] ∧ signed true [1] < signed false [0] := by
  exact ⟨Canonical.zero, Canonical.positive 1 [] (by decide),
    fun _ => by decide, True.intro, by decide⟩

#print axioms SignedDecimal.widthBound
#print axioms SignedDecimal.headBefore
#print axioms SignedDecimal.lexOrder
#print axioms SignedDecimal.widthBefore
#print axioms SignedDecimal.magnitudeOrder
#print axioms SignedDecimal.decimalOrder

end SignedDecimal

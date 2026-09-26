"""
Feature 7 — Proof of Payment Verifier (real logic, FEATURES.md §7).
Rule-based amount matching. OCR/receipt-text-extraction is an explicitly
out-of-scope stretch goal (see FEATURES.md §7 and PHASES.md Phase 0 log) —
this compares the claimed amount against the expected amount only.
"""

from schemas import VerifyPaymentRequest, VerifyPaymentResponse

TOLERANCE = 0.01  # guard against float comparison noise, not a business rule


def verify_payment(payload: VerifyPaymentRequest) -> VerifyPaymentResponse:
    diff = abs(payload.claimed_amount - payload.expected_amount)
    if diff <= TOLERANCE:
        return VerifyPaymentResponse(
            status="auto-matched",
            reason=f"Claimed amount matches expected amount ({payload.expected_amount}).",
        )
    return VerifyPaymentResponse(
        status="flagged",
        reason=(
            f"Claimed amount {payload.claimed_amount} does not match "
            f"expected amount {payload.expected_amount} — needs admin review."
        ),
    )

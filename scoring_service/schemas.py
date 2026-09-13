from typing import List
from pydantic import BaseModel


class ScoreRepairRequest(BaseModel):
    description: str
    category: str  # electrical | plumbing | structural | appliance | other
    has_media: bool
    hours_since_submission: float = 0


class ScoreRepairResponse(BaseModel):
    score: float
    tier: str  # critical | high | medium | low
    matched_keywords: List[str] = []


class VerifyPaymentRequest(BaseModel):
    expected_amount: float
    claimed_amount: float
    proof_filename: str


class VerifyPaymentResponse(BaseModel):
    status: str  # auto-matched | flagged
    reason: str

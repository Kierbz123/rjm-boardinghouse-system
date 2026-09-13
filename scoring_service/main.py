"""
RJM Boardinghouse — local scoring microservice.
Binds to 127.0.0.1 only (never 0.0.0.0) per CLAUDE.md's hard rules and
.claude/rules/python-conventions.md. Stateless: no DB access here — PHP
is the only writer to MariaDB (see ARCHITECTURE.md §2).

Run: python3 main.py   (or: uvicorn main:app --host 127.0.0.1 --port 5000)
"""

from fastapi import FastAPI

from schemas import (
    ScoreRepairRequest,
    ScoreRepairResponse,
    VerifyPaymentRequest,
    VerifyPaymentResponse,
)
from scoring import score_repair
from verification import verify_payment

app = FastAPI(title="RJM Boardinghouse Scoring Service")


@app.post("/score-repair", response_model=ScoreRepairResponse)
def score_repair_endpoint(payload: ScoreRepairRequest) -> ScoreRepairResponse:
    return score_repair(payload)


@app.post("/verify-payment", response_model=VerifyPaymentResponse)
def verify_payment_endpoint(payload: VerifyPaymentRequest) -> VerifyPaymentResponse:
    return verify_payment(payload)


if __name__ == "__main__":
    import uvicorn

    uvicorn.run(app, host="127.0.0.1", port=5000)

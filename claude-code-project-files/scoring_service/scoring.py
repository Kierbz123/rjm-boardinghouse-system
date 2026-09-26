"""
Feature 1 — Smart AI Repair Priority System (real logic, FEATURES.md §1).
Weighted rule engine: keyword severity + category base weight + media bonus
+ time-decay. No ML, no external calls — matches the localhost/offline
constraint in CLAUDE.md.
"""

from schemas import ScoreRepairRequest, ScoreRepairResponse

KEYWORD_WEIGHTS = {
    # Safety emergencies (highest priority)
    "gas leak": 45,
    "exposed wire": 40,
    "electrocut": 40,
    "fire": 40,
    "smoke": 32,
    "gas": 35,
    "sparking": 32,
    "ceiling collapse": 45,
    "flooding": 32,
    "flood": 30,
    
    # Security and theft (critical)
    "stole": 45,
    "stolen": 45,
    "theft": 45,
    "thief": 45,
    "robbery": 45,
    "break-in": 40,
    "burglary": 40,
    "intruder": 40,
    "forced entry": 40,
    "assault": 45,
    "attack": 40,
    "threat": 35,
    "weapon": 45,
    "cctv": 20,
    "camera": 15,
    "security": 25,
    
    # Injury and medical emergencies (critical)
    "injured": 45,
    "injury": 45,
    "hurt": 35,
    "bleeding": 45,
    "blood": 40,
    "unconscious": 45,
    "fainted": 40,
    "medical": 35,
    "emergency": 45,
    "ambulance": 45,
    "hospital": 40,
    
    # Vehicle and structural damage (high)
    "crash": 40,
    "crashed": 40,
    "collision": 40,
    "accident": 40,
    "vehicle": 25,
    "car": 25,
    "door destroyed": 40,
    "window broken": 35,
    "smashed": 30,
    "structural damage": 35,
    
    # Building issues (medium/high)
    "no water": 20,
    "leak": 15,
    "broken lock": 20,
    "no lights": 12,
    
    # Minor issues (low)
    "squeaky": 2,
    "cosmetic": 2,
    "scratch": 2,
    "loose handle": 4,
    "paint": 3,
}

CATEGORY_BASE_WEIGHT = {
    "electrical": 20,
    "plumbing": 15,
    "structural": 25,
    "appliance": 8,
    "other": 5,
}

MEDIA_BONUS = 10
MAX_TIME_DECAY_BONUS = 20
TIME_DECAY_PER_HOUR = 0.5


def _tier_for_score(score: float) -> str:
    if score >= 70:
        return "critical"
    if score >= 45:
        return "high"
    if score >= 20:
        return "medium"
    return "low"


def score_repair(payload: ScoreRepairRequest) -> ScoreRepairResponse:
    text = payload.description.lower()
    matched = [kw for kw in KEYWORD_WEIGHTS if kw in text]
    keyword_score = sum(KEYWORD_WEIGHTS[kw] for kw in matched)

    base = CATEGORY_BASE_WEIGHT.get(payload.category, 5)
    media_bonus = MEDIA_BONUS if payload.has_media else 0
    decay = min(payload.hours_since_submission * TIME_DECAY_PER_HOUR, MAX_TIME_DECAY_BONUS)

    score = min(base + keyword_score + media_bonus + decay, 100)
    tier = _tier_for_score(score)

    return ScoreRepairResponse(score=round(score, 2), tier=tier, matched_keywords=matched)

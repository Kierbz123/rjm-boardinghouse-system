-- Found during a performance audit: billing_period is filtered in both
-- Payment::hasVerifiedPaymentForPeriod() and the bulk
-- Payment::verifiedBoarderIdsForPeriod() added to fix an N+1 pattern in
-- PenaltyEngine/PenaltyController, but had no supporting index.
ALTER TABLE payments ADD INDEX idx_payments_billing_period (billing_period, boarder_id);

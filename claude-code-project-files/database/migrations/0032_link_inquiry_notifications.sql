-- Inquiry notifications used to link to the inquiry-center form, which shows no inquiry.
-- Notifications now carry entity_type = 'inquiry' + entity_id, and the link is built from entity_id.
--
-- 1) Notifications created before this change have no entity (NULL). Recover it from the inquiries
--    table instead of parsing the text: each inquiry id is matched against the exact message prefix
--    "New room inquiry #<id> from ", so a "#" inside a name can never produce a wrong id, and a
--    notification whose inquiry no longer exists is left alone (no link to a missing page).
UPDATE notifications n
JOIN inquiries i ON n.message LIKE CONCAT('New room inquiry #', i.id, ' from %')
SET n.entity_type = 'inquiry', n.entity_id = i.id
WHERE n.type = 'inquiry' AND n.entity_id IS NULL;

-- 2) Build the link from entity_id. Safe to run again: it only ever writes the same value.
UPDATE notifications
SET action_url = CONCAT('/admin/inquiries/', entity_id)
WHERE type = 'inquiry' AND entity_type = 'inquiry' AND entity_id IS NOT NULL;

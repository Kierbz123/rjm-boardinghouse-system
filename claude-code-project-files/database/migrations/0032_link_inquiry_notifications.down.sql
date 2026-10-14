-- Back to the old state: no entity, link to the inquiry-center page.
UPDATE notifications
SET action_url = '/admin/inquiry-center', entity_type = NULL, entity_id = NULL
WHERE type = 'inquiry' AND entity_type = 'inquiry';

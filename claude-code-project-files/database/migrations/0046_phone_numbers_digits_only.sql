-- Phone numbers are digits only (App\Support\Phone, and data-digits on the forms). The controllers
-- normalise everything written from now on; these are the rows stored before that rule existed,
-- where a leading "+", spaces, dashes or brackets were kept verbatim.
--
-- Only rows that contain at least one digit are touched. A legacy value with no digit in it at all
-- ("ask my mother") is left exactly as it is for a person to look at, rather than being silently
-- turned into an empty number; the app itself now refuses such input, so no new ones can appear.
-- Because every updated row keeps at least one digit, the result is never empty and the nullable
-- columns need no NULL handling here.

UPDATE boarder_profiles
SET contact_number = REGEXP_REPLACE(contact_number, '[^0-9]', '')
WHERE contact_number REGEXP '[0-9]';

UPDATE boarder_profiles
SET emergency_contact_number = REGEXP_REPLACE(emergency_contact_number, '[^0-9]', '')
WHERE emergency_contact_number REGEXP '[0-9]';

UPDATE users
SET phone = REGEXP_REPLACE(phone, '[^0-9]', '')
WHERE phone REGEXP '[0-9]';

UPDATE inquiries
SET phone = REGEXP_REPLACE(phone, '[^0-9]', '')
WHERE phone REGEXP '[0-9]';

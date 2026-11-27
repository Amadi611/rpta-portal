START TRANSACTION;

UPDATE faqs
SET question = REPLACE(question, 'RPTA', 'SPRPTA')
WHERE question LIKE '%RPTA%'
  AND question NOT LIKE '%SPRPTA%';

UPDATE faqs
SET answer = REPLACE(answer, 'RPTA', 'SPRPTA')
WHERE answer LIKE '%RPTA%'
  AND answer NOT LIKE '%SPRPTA%';

UPDATE chat_messages
SET message = REPLACE(message, 'RPTA', 'SPRPTA')
WHERE sender = 'bot'
  AND message LIKE '%RPTA%'
  AND message NOT LIKE '%SPRPTA%';

COMMIT;

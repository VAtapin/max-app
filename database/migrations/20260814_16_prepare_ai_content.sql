ALTER TABLE test_scale_results MODIFY COLUMN ai_enabled TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE test_results MODIFY COLUMN ai_enabled TINYINT(1) NOT NULL DEFAULT 1;

UPDATE test_scale_results
SET ai_enabled = 1,
    content_status = IF(content_status = 'draft', 'review', content_status),
    exclusions_text = COALESCE(NULLIF(exclusions_text, ''), 'Не трактовать результат как диагноз, не назначать лечение и не добавлять утверждения, которых нет в утверждённом тексте результата.'),
    escalation_text = COALESCE(NULLIF(escalation_text, ''), 'Передать диалог консультанту при жалобах на самочувствие, вопросах о лечении, беременности, противопоказаниях или просьбе о персональной консультации.'),
    next_review_at = COALESCE(next_review_at, DATE_ADD(CURRENT_DATE, INTERVAL 1 YEAR));

UPDATE test_results
SET ai_enabled = 1,
    content_status = IF(content_status = 'draft', 'review', content_status),
    exclusions_text = COALESCE(NULLIF(exclusions_text, ''), 'Не трактовать результат как диагноз, не назначать лечение и не добавлять утверждения, которых нет в утверждённом тексте результата.'),
    escalation_text = COALESCE(NULLIF(escalation_text, ''), 'Передать диалог консультанту при жалобах на самочувствие, вопросах о лечении, беременности, противопоказаниях или просьбе о персональной консультации.'),
    next_review_at = COALESCE(next_review_at, DATE_ADD(CURRENT_DATE, INTERVAL 1 YEAR));

UPDATE products
SET ai_enabled = 1,
    content_status = IF(content_status = 'draft', 'review', content_status),
    allowed_claims = COALESCE(NULLIF(allowed_claims, ''), 'Использовать только сведения, прямо указанные в карточке продукта. Не обещать лечение, гарантированный результат или замену консультации специалиста.'),
    next_review_at = COALESCE(next_review_at, DATE_ADD(CURRENT_DATE, INTERVAL 1 YEAR))
WHERE is_deleted = 0;

UPDATE consultant_profiles
SET short_description = COALESCE(NULLIF(short_description, ''), 'Специалист SWPro. Помогает разобраться в материалах, результатах чек-апа и следующих доступных шагах.'),
    ai_tone = COALESCE(NULLIF(ai_tone, ''), 'friendly'),
    ai_address_form = COALESCE(ai_address_form, 'adaptive'),
    ai_greeting_style = COALESCE(NULLIF(ai_greeting_style, ''), 'Обращайся естественно и доброжелательно. Используй имя не чаще одного раза в сообщении, не перечисляй данные анкеты.'),
    ai_persona_notes = COALESCE(NULLIF(ai_persona_notes, ''), 'Спокойно выясняй цель вопроса, отвечай только по утверждённым материалам SWPro и предлагай один понятный следующий шаг.'),
    ai_forbidden_phrases = COALESCE(NULLIF(ai_forbidden_phrases, ''), 'Диагнозы, назначения лечения, гарантии результата, давление на покупку, выдуманные свойства продуктов.'),
    ai_handoff_rules = COALESCE(NULLIF(ai_handoff_rules, ''), 'Передавай диалог человеку при жалобах на самочувствие, вопросах о лечении или противопоказаниях, конфликте, недовольстве, просьбе связаться лично либо отсутствии подтверждённого ответа.');

INSERT INTO ai_conversation_scenarios
  (owner_type, owner_id, event_key, channel, audience, title, template_text, allowed_variables, priority, is_active, is_approved, approved_at)
SELECT 'superadmin', 0, 'welcome', 'any', 'client', 'Первое приветствие',
       '{{first_name}}, здравствуйте! Рада знакомству. Здесь можно спокойно пройти чек-ап, посмотреть результат и задать вопросы по доступным материалам. Расскажите, что для вас сейчас важнее всего?',
       JSON_ARRAY('first_name','consultant_name','test_title','days','city'), 100, 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM ai_conversation_scenarios WHERE owner_type = 'superadmin' AND owner_id = 0 AND event_key = 'welcome' AND channel = 'any');

INSERT INTO ai_conversation_scenarios
  (owner_type, owner_id, event_key, channel, audience, title, template_text, allowed_variables, priority, is_active, is_approved, approved_at)
SELECT 'superadmin', 0, 'test_result', 'any', 'client', 'После завершения чек-апа',
       '{{first_name}}, ваш чек-ап завершён, результат сохранён. Давайте разберём его без спешки: что показалось вам самым важным или вызвало вопрос?',
       JSON_ARRAY('first_name','consultant_name','test_title','days','city'), 100, 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM ai_conversation_scenarios WHERE owner_type = 'superadmin' AND owner_id = 0 AND event_key = 'test_result' AND channel = 'any');

INSERT INTO ai_conversation_scenarios
  (owner_type, owner_id, event_key, channel, audience, title, template_text, allowed_variables, priority, is_active, is_approved, approved_at)
SELECT 'superadmin', 0, 'retest', 'any', 'client', 'Приглашение на повторный чек-ап',
       '{{first_name}}, после прошлого чек-апа прошло около {{days}} дней. Если хотите, можно пройти его ещё раз и спокойно сравнить изменения. Начнём сейчас или сначала обсудим прошлый результат?',
       JSON_ARRAY('first_name','consultant_name','test_title','days','city'), 100, 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM ai_conversation_scenarios WHERE owner_type = 'superadmin' AND owner_id = 0 AND event_key = 'retest' AND channel = 'any');

INSERT INTO ai_conversation_scenarios
  (owner_type, owner_id, event_key, channel, audience, title, template_text, allowed_variables, priority, is_active, is_approved, approved_at)
SELECT 'superadmin', 0, 'inactive', 'any', 'client', 'Возвращение после перерыва',
       '{{first_name}}, давно не общались. Как у вас дела? Если появились вопросы по материалам, продуктам или прошлому чек-апу, напишите — разберёмся вместе.',
       JSON_ARRAY('first_name','consultant_name','test_title','days','city'), 100, 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM ai_conversation_scenarios WHERE owner_type = 'superadmin' AND owner_id = 0 AND event_key = 'inactive' AND channel = 'any');

INSERT INTO ai_conversation_scenarios
  (owner_type, owner_id, event_key, channel, audience, title, template_text, allowed_variables, priority, is_active, is_approved, approved_at)
SELECT 'superadmin', 0, 'plan_ending', 'any', 'client', 'Завершение плана',
       '{{first_name}}, ваш текущий план подходит к завершению. Предлагаю коротко посмотреть, что получилось выполнить, что было неудобно и какой следующий шаг будет реалистичным.',
       JSON_ARRAY('first_name','consultant_name','test_title','days','city'), 100, 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM ai_conversation_scenarios WHERE owner_type = 'superadmin' AND owner_id = 0 AND event_key = 'plan_ending' AND channel = 'any');

INSERT INTO ai_conversation_scenarios
  (owner_type, owner_id, event_key, channel, audience, title, template_text, allowed_variables, priority, is_active, is_approved, approved_at)
SELECT 'superadmin', 0, 'birthday', 'any', 'client', 'Поздравление с днём рождения',
       '{{first_name}}, поздравляю вас с днём рождения! Желаю хорошего самочувствия, энергии и приятных событий. Если захотите, помогу подобрать полезные материалы без спешки и рекламного давления.',
       JSON_ARRAY('first_name','consultant_name','test_title','days','city'), 100, 1, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM ai_conversation_scenarios WHERE owner_type = 'superadmin' AND owner_id = 0 AND event_key = 'birthday' AND channel = 'any');

INSERT INTO ai_recommendation_rules
  (test_result_id, target_type, target_id, rule_type, rationale, priority, is_active, is_approved, approved_at)
SELECT tr.id, 'product', tr.product_id, 'include',
       'Продукт уже явно выбран для этого диапазона в конструкторе теста.', 100, 1, 1, NOW()
FROM test_results tr
JOIN products p ON p.id = tr.product_id AND p.is_active = 1 AND p.is_deleted = 0 AND p.ai_enabled = 1 AND p.content_status = 'approved'
WHERE tr.product_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM ai_recommendation_rules r
    WHERE r.test_result_id = tr.id AND r.target_type = 'product' AND r.target_id = tr.product_id
  );

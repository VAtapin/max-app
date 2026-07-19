UPDATE end_users
SET first_name = NULL,
    last_name = NULL
WHERE platform IN ('VK', 'OK', 'web')
  AND first_name IN ('VK', 'Web')
  AND last_name = 'User';

UPDATE platform_accounts
SET first_name = NULL,
    last_name = NULL,
    display_name = NULL
WHERE platform IN ('VK', 'OK', 'web')
  AND (
    (first_name IN ('VK', 'Web') AND last_name = 'User')
    OR display_name IN ('VK User', 'Web User')
  );

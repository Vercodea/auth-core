SELECT
    id,
    username,
    password,
    email
FROM
    users
WHERE
    username = ?
    OR email = ?
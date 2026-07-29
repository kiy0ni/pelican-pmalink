# PmaLink

Adds a phpMyAdmin SSO button to the database list in the Pelican server area.
Clicking it generates a single-use token, hands it to phpMyAdmin, and logs the
user straight into the correct database.

## How it works

1. The user clicks **phpMyAdmin** next to one of their databases.
2. Pelican checks that the user may access that server, then stores the
   credentials in the cache under a hashed, single-use token valid for 60 seconds.
3. The user is redirected to `signon.php` on your phpMyAdmin instance.
4. `signon.php` calls back to `/pmalink/verify/{token}` on the panel, receives the
   credentials, and starts a phpMyAdmin signon session.

The token is consumed on first read and never appears in the database or the logs.

## Panel setup

Install the plugin, then open **Admin → Plugins → PmaLink → Settings** and set the
base URL of your phpMyAdmin instance, without a trailing slash. This writes
`PMALINK_PMA_URL` to your `.env`.

The button stays hidden until this URL is configured.

## phpMyAdmin setup

SSH into the server hosting phpMyAdmin and create `signon.php` in the root of your
phpMyAdmin directory. Replace `PELICAN_URL` with your own panel URL.

```php
<?php

declare(strict_types=1);

const PELICAN_URL = 'https://panel.your-domain.com';
const SIGNON_SESSION = 'SignonSession';

$token = $_GET['token'] ?? '';

if (!preg_match('/^[A-Za-z0-9]{64}$/', $token)) {
    exit('SSO Error: missing or malformed token.');
}

$ch = curl_init(PELICAN_URL . '/pmalink/verify/' . rawurlencode($token));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
]);

$body = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($body === false || $status !== 200) {
    exit('SSO Error: HTTP ' . $status . ' ' . $error);
}

$data = json_decode($body, true);

if (!is_array($data) || empty($data['user'])) {
    exit('SSO Error: invalid payload.');
}

session_set_cookie_params(0, '/', '', true, true);
session_name(SIGNON_SESSION);
session_start();

$_SESSION = [];

$_SESSION['PMA_single_signon_user'] = $data['user'];
$_SESSION['PMA_single_signon_password'] = $data['password'];
$_SESSION['PMA_single_signon_host'] = $data['host'];
$_SESSION['PMA_single_signon_port'] = (string) ($data['port'] ?? 3306);

session_write_close();

header('Location: index.php');
exit;
```

Then add this to your phpMyAdmin `config.inc.php`:

```php
$cfg['Servers'][$i]['auth_type']     = 'signon';
$cfg['Servers'][$i]['SignonSession'] = 'SignonSession';
$cfg['Servers'][$i]['SignonURL']     = 'signon.php';
```

## Troubleshooting

`SSO Error: HTTP 404` — the token expired or was already used. Tokens last 60
seconds and work once; reloading `signon.php` will always fail.

`SSO Error: HTTP 302` — the verify route ended up behind authentication. Check
`php artisan route:list --path=pmalink -v`; the route must carry only the
throttle middleware.

`SSO Error: HTTP 403` or `503` — a WAF such as Cloudflare is blocking the
server-to-server call. Point `PELICAN_URL` at the panel's internal address and
pass the correct `Host` header.

`SSO Error: HTTP 0` — cURL could not connect at all. Usually DNS, a firewall, or
an untrusted TLS certificate on the phpMyAdmin host.

## License

MIT

## Shared secret

The verify endpoint is protected by a shared secret so that only your phpMyAdmin
host can exchange a token for credentials. Open **Admin → Plugins → PmaLink →
Settings**, copy the generated value, and place it in your `signon.php`:

```php
const VERIFY_SECRET = 'paste-the-value-here';
```

It is sent as the `X-PmaLink-Secret` header. Without it the endpoint answers
`403`; if no secret is configured on the panel side it answers `503`.

Keep `signon.php` unreadable by other users, for example `chmod 640` with the
web server user as group owner.

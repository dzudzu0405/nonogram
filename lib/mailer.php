<?php
// Email over SMTP, written straight against the protocol (no Composer, no PHPMailer).
// Sending through a real mailbox on your own domain keeps mail out of spam; PHP's mail() usually has no SPF/DKIM.
// driver 'log' sends nothing and appends the message to storage/logs/mail.log, for trying flows locally.
// Ported from GameCraft Studio's Mailer.

const MAIL_TIMEOUT = 20;

function mail_config(): array
{
    $c = (array) config('mail', []);
    if (empty($c['from_email'])) $c['from_email'] = (string) config('mail_from', $c['username'] ?? '');
    if (empty($c['from_name'])) $c['from_name'] = (string) config('app_name', 'Nonogram Studio');
    $c['driver'] = $c['driver'] ?? 'smtp';
    return $c;
}

/** Can mail reach a real inbox? Pages that promise "check your email" use this. */
function mail_ready(): bool
{
    $c = mail_config();
    return $c['driver'] === 'smtp' && !empty($c['host']) && !empty($c['username']) && !empty($c['from_email']);
}

/** Can pages that say "check your email" run? Real SMTP, or the log driver on a debug (local) site only:
    a buyer cannot read storage/logs/mail.log, so the log driver must never make that promise on a live site. */
function mail_usable(): bool
{
    return mail_ready() || (mail_config()['driver'] === 'log' && config('debug'));
}

function mail_last_error(?string $set = null): ?string
{
    static $err = null;
    if (func_num_args()) $err = $set;
    return $err;
}

/** A simple branded HTML email. $paras are plain text; $button = [label, url]. */
function mail_layout(string $heading, array $paras, ?array $button = null, string $footer = ''): string
{
    $app = e(config('app_name', 'Nonogram Studio'));
    $p = '';
    foreach ($paras as $t) $p .= '<p style="margin:0 0 14px;font-size:15px;line-height:1.55;color:#2a3138">' . e($t) . '</p>';
    $b = $button ? '<p style="margin:22px 0"><a href="' . e($button[1]) . '" style="display:inline-block;background:#1d4ed8;color:#fff;text-decoration:none;font-weight:600;padding:12px 20px;border-radius:8px">' . e($button[0]) . '</a></p>'
        . '<p style="margin:0 0 14px;font-size:13px;color:#5a6570">If the button does not work, copy this address into your browser:<br>' . e($button[1]) . '</p>' : '';
    $f = $footer !== '' ? '<p style="margin:18px 0 0;font-size:12px;color:#8a949e">' . e($footer) . '</p>' : '';
    return '<!doctype html><html><body style="margin:0;background:#eef1f4;font-family:Segoe UI,Arial,sans-serif">'
        . '<div style="max-width:560px;margin:0 auto;padding:28px 16px"><div style="font-weight:800;font-size:18px;color:#14181d;margin-bottom:14px">' . $app . '</div>'
        . '<div style="background:#fff;border:1px solid #d6dce2;border-radius:12px;padding:26px">'
        . '<h1 style="margin:0 0 16px;font-size:20px;color:#14181d">' . e($heading) . '</h1>' . $p . $b . $f . '</div></div></body></html>';
}

function mail_html_to_text(string $html): string
{
    $t = preg_replace('#<(script|style)\b.*?</\1>#is', '', $html) ?? $html;
    $t = preg_replace_callback('#<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', function ($m) {
        $label = trim(strip_tags($m[2])); return $label === '' || $label === $m[1] ? $m[1] : $label . ' ( ' . $m[1] . ' )';
    }, $t) ?? $t;
    $t = preg_replace('#<(br|/p|/div|/h[1-6]|/tr|/li)\s*/?>#i', "\n", $t) ?? $t;
    $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", array_map('trim', explode("\n", $t)))));
}

/** Sends one message. Never throws: returns false and sets mail_last_error() instead. */
function mail_send(string $to, string $toName, string $subject, string $html): bool
{
    mail_last_error(null);
    $c = mail_config();
    if (($c['enabled'] ?? true) === false) { mail_last_error('Email is switched off in config.php.'); return false; }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { mail_last_error('The recipient address is not valid.'); return false; }
    if (empty($c['from_email'])) { mail_last_error('No sender address (mail.from_email in config.php).'); return false; }
    $msg = mail_build($to, $toName, (string) $c['from_email'], (string) $c['from_name'], $subject, $html, $c);
    if ($c['driver'] === 'log') {
        $dir = APP_ROOT . '/storage/logs'; @mkdir($dir, 0700, true);
        $entry = str_repeat('=', 70) . "\n" . gmdate('Y-m-d H:i:s') . " UTC  TO: $to  SUBJECT: $subject\n" . str_repeat('=', 70) . "\n" . $msg . "\n\n";
        return (bool) @file_put_contents($dir . '/mail.log', $entry, FILE_APPEND);
    }
    try {
        return mail_smtp((string) $c['from_email'], $to, $msg, $c);
    } catch (Throwable $e) {
        mail_last_error($e->getMessage());
        error_log('[nonogram mail] ' . $e->getMessage());
        return false;
    }
}

function mail_header_encode(string $v): string
{
    return preg_match('/^[\x20-\x7E]*$/', $v) ? $v : '=?UTF-8?B?' . base64_encode($v) . '?=';
}

function mail_address(string $name, string $email): string
{
    $name = trim(str_replace(["\r", "\n", '"'], '', $name));
    return $name === '' ? $email : mail_header_encode($name) . ' <' . $email . '>';
}

function mail_build(string $to, string $toName, string $from, string $fromName, string $subject, string $html, array $c): string
{
    $boundary = 'ns_' . bin2hex(random_bytes(12));
    $domain = substr(strrchr($from, '@') ?: '@localhost', 1);
    $h = [
        'Date: ' . date('r'),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
        'From: ' . mail_address($fromName, $from),
        'To: ' . mail_address($toName, $to),
    ];
    if (!empty($c['reply_to'])) $h[] = 'Reply-To: ' . str_replace(["\r", "\n"], '', (string) $c['reply_to']);
    $h[] = 'Subject: ' . mail_header_encode(str_replace(["\r", "\n"], ' ', $subject));
    $h[] = 'MIME-Version: 1.0';
    $h[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
    $body = [
        '--' . $boundary, 'Content-Type: text/plain; charset=UTF-8', 'Content-Transfer-Encoding: quoted-printable', '', quoted_printable_encode(mail_html_to_text($html)), '',
        '--' . $boundary, 'Content-Type: text/html; charset=UTF-8', 'Content-Transfer-Encoding: quoted-printable', '', quoted_printable_encode($html), '',
        '--' . $boundary . '--',
    ];
    return implode("\r\n", $h) . "\r\n\r\n" . implode("\r\n", $body);
}

function mail_smtp(string $from, string $to, string $message, array $c): bool
{
    $host = (string) ($c['host'] ?? ''); $port = (int) ($c['port'] ?? 465);
    $enc = strtolower((string) ($c['encryption'] ?? 'ssl'));
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false]]);
    $s = @stream_socket_client(($enc === 'ssl' ? 'ssl://' : '') . $host . ':' . $port, $errNo, $errStr, MAIL_TIMEOUT, STREAM_CLIENT_CONNECT, $ctx);
    if (!$s) throw new RuntimeException("Could not reach the mail server at $host:$port" . ($errStr ? " - $errStr" : '') . '. Check host and port, and that your hosting allows outbound SMTP.');
    stream_set_timeout($s, MAIL_TIMEOUT);
    try {
        smtp_expect($s, 220);
        $helo = preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?: substr(strrchr($from, '@') ?: '@localhost', 1);
        smtp_cmd($s, 'EHLO ' . $helo, 250);
        if ($enc === 'tls') {
            smtp_cmd($s, 'STARTTLS', 220);
            if (!@stream_socket_enable_crypto($s, true, STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) throw new RuntimeException('The server refused to start TLS. Try port 465 with encryption ssl.');
            smtp_cmd($s, 'EHLO ' . $helo, 250);
        }
        if (!empty($c['username'])) {
            smtp_cmd($s, 'AUTH LOGIN', 334);
            smtp_cmd($s, base64_encode((string) $c['username']), 334);
            smtp_cmd($s, base64_encode((string) ($c['password'] ?? '')), 235);
        }
        smtp_cmd($s, 'MAIL FROM:<' . $from . '>', 250);
        smtp_cmd($s, 'RCPT TO:<' . $to . '>', 250);
        smtp_cmd($s, 'DATA', 354);
        smtp_write($s, preg_replace('/^\./m', '..', $message) . "\r\n.");
        smtp_expect($s, 250);
        smtp_write($s, 'QUIT');
        return true;
    } finally {
        @fclose($s);
    }
}

function smtp_cmd($s, string $line, int $code): string { smtp_write($s, $line); return smtp_expect($s, $code); }

function smtp_write($s, string $line): void
{
    if (@fwrite($s, $line . "\r\n") === false) throw new RuntimeException('Lost the connection to the mail server.');
}

function smtp_expect($s, int $expected): string
{
    $reply = '';
    while (true) {
        $line = @fgets($s, 8192);
        if ($line === false) throw new RuntimeException(!empty(stream_get_meta_data($s)['timed_out']) ? 'The mail server stopped responding.' : 'No reply from the mail server.');
        $reply .= $line;
        if (strlen($line) < 4 || $line[3] !== '-') break;
    }
    $code = (int) substr($reply, 0, 3);
    if ($code !== $expected) {
        $hint = match ($code) {
            535 => 'The mail server rejected the username or password (the username is normally the full email address).',
            530 => 'The mail server wants a username and password.',
            550 => 'The mail server refused the address. Check that mail.from_email is a real mailbox on your domain.',
            554 => 'The mail server rejected the message, often because the sender domain does not match the mailbox.',
            default => 'The mail server replied with an error.',
        };
        throw new RuntimeException($hint . ' (server said: ' . trim($reply) . ')');
    }
    return $reply;
}

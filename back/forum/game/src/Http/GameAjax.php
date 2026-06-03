<?php
declare(strict_types=1);

namespace Game\Http;

/**
 * Respuestas JSON y guards estándar para game/ajax/*.
 */
final class GameAjax
{
    public static function json(bool $ok, $data = null, ?array $error = null, int $httpCode = 200): void
    {
        if ($httpCode !== 200) {
            http_response_code($httpCode);
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => $ok,
            'data' => $data,
            'error' => $error,
            'meta' => null,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function fail(int $httpCode, string $message, ?int $errorCode = null): void
    {
        self::json(false, null, [
            'code' => $errorCode ?? $httpCode,
            'message' => $message,
        ], $httpCode);
    }

    public static function requireLogin(): int
    {
        global $mybb;
        $uid = (int)($mybb->user['uid'] ?? 0);
        if ($uid === 0) {
            self::json(false, null, ['code' => 401, 'message' => 'No autorizado.'], 401);
        }
        return $uid;
    }

    public static function requirePost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            self::json(false, null, ['code' => 405, 'message' => 'Method not allowed'], 405);
        }
    }

    /**
     * @param array<string, mixed>|null $input JSON body o $_POST
     */
    public static function requireCsrf(?array $input = null): void
    {
        $input = $input ?? self::postInput();
        $key = (string)(
            $input['my_post_key']
            ?? $_POST['my_post_key']
            ?? $_SERVER['HTTP_X_MYBB_POST_KEY']
            ?? ''
        );
        if ($key === '' || !verify_post_check($key, false)) {
            self::json(false, null, ['code' => 403, 'message' => 'Token de sesión inválido. Recarga la página.'], 403);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function postInput(): array
    {
        $ct = (string)($_SERVER['CONTENT_TYPE'] ?? '');
        if (stripos($ct, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw ?: '', true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return is_array($_POST) ? $_POST : [];
    }

    /** @return array<string, mixed> */
    public static function postJson(): array
    {
        $input = self::postInput();
        if ($input === []) {
            self::json(false, null, ['code' => 400, 'message' => 'Payload inválido'], 400);
        }
        return $input;
    }
}

<?php

namespace App\Support;

/**
 * The one place that decides what request input is acceptable.
 *
 * Every controller reads $_POST / $_GET / JSON values as plain text (trim((string) ...)). A visitor
 * who sends `name[]=x` or a NUL byte instead used to reach code that was never written for it and
 * got a 500. guardRequest() runs once, in the front controller, before any route: input that is not
 * plain text is refused with a clean 400, so no controller has to defend itself.
 */
final class Input
{
    /** Why this input is unacceptable, or null when every value is plain text without NUL bytes. */
    public static function problem(array $params, array $allowListKeys = []): ?string
    {
        foreach ($params as $key => $value) {
            if (is_string($key) && str_contains($key, "\0")) {
                return 'A field name contains a NUL byte.';
            }
            if (is_array($value)) {
                // Lists of plain text are only accepted where a caller names the field.
                $isAllowedList = in_array($key, $allowListKeys, true) && array_is_list($value) && !array_filter($value, fn ($v) => !is_string($v) || str_contains($v, "\0"));
                if (!$isAllowedList) {
                    return "The field \"{$key}\" must be plain text.";
                }
                continue;
            }
            if (is_string($value) && str_contains($value, "\0")) {
                return "The field \"{$key}\" contains a NUL byte.";
            }
            if (!is_scalar($value) && $value !== null) {
                return "The field \"{$key}\" must be plain text.";
            }
        }
        return null;
    }

    /** Refuses the whole request with a 400 when its query string or form fields are not plain text. */
    public static function guardRequest(): void
    {
        if (self::problem($_GET) !== null || self::problem($_POST) !== null) {
            ErrorPage::render(400);
        }
    }

    /**
     * The decoded JSON body of this request, or null when it is not a JSON object whose values are
     * all plain text (the only lists allowed are the fields named in $allowListKeys).
     */
    public static function jsonBody(array $allowListKeys = []): ?array
    {
        $data = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($data) || array_is_list($data) && $data !== [] || self::problem($data, $allowListKeys) !== null) {
            return null;
        }
        return $data;
    }
}

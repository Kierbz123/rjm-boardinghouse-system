<?php

namespace App\Services;

use App\Support\NavRegistry;

/**
 * The assistant's answers that need no AI model. Everything here is decided by
 * PHP rules from the logged-in role, so it works with Ollama stopped and can
 * never offer a page the role cannot open.
 */
class AssistantService
{
    private const OPEN = ['en' => 'Open %s', 'tl' => 'Buksan ang %s'];

    /**
     * @return array{reply:string, actions:list<array{label:string,href:string}>, source:string}|null
     *         null when no rule matches; the caller may then fall back to free-form chat
     */
    public static function answer(string $message, string $role, string $lang = 'en'): ?array
    {
        $lang = $lang === 'tl' ? 'tl' : 'en';
        $pages = NavRegistry::find($role, $message);
        if (!$pages) {
            return null;
        }

        // The best match explains itself; up to two other matches are offered as buttons only.
        $actions = [];
        foreach ($pages as $page) {
            $actions[$page['label']] ??= ['label' => sprintf(self::OPEN[$lang], $page['label']), 'href' => $page['href']];
        }

        return [
            'reply' => $pages[0]['about'][$lang],
            'actions' => array_slice(array_values($actions), 0, 3),
            'source' => 'pages',
        ];
    }
}

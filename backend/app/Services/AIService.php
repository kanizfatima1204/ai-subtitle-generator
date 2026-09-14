<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class AIService
{
    public function assist(
        string $text,
        string $action,
        ?string $targetLanguage = null
    ): array {
        $url = rtrim(
            config('services.ai.url'),
            '/'
        );

        if (!$url) {
            throw new RuntimeException(
                'AI service URL is not configured.'
            );
        }

        $response = Http::timeout(120)
            ->connectTimeout(15)
            ->acceptJson()
            ->post(
                $url . '/ai-assist',
                [
                    'text' => $text,
                    'action' => $action,
                    'target_language' => $targetLanguage,
                ]
            );

        $response->throw();

        $data = $response->json();

        if (!is_array($data)) {
            throw new RuntimeException(
                'Invalid response from AI service.'
            );
        }

        return $data;
    }
}
<?php

namespace App\Services;

use App\Enums\AiRunFeature;
use App\Models\DemoSession;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DictationSessionService
{
    public function __construct(private AiUsageRecorder $recorder) {}

    /**
     * @return array{client_secret: string, expires_at: string|null, max_seconds: int}
     */
    public function mint(DemoSession $session): array
    {
        $key = (string) config('ai.providers.openai.key');

        if ($key === '') {
            throw new RuntimeException('Dictation is unavailable.');
        }

        $run = $this->recorder->start(
            AiRunFeature::Transcription,
            null,
            (string) config('supportflow.dictation.model'),
        );

        try {
            $response = Http::withToken($key)
                ->acceptJson()
                ->withHeaders([
                    'OpenAI-Safety-Identifier' => hash('sha256', 'supportflow-demo|'.$session->id),
                ])
                ->timeout(15)
                ->post(rtrim((string) config('ai.providers.openai.url'), '/').'/realtime/client_secrets', [
                    'expires_after' => [
                        'anchor' => 'created_at',
                        'seconds' => (int) config('supportflow.dictation.max_seconds', 120),
                    ],
                    'session' => [
                        'type' => 'transcription',
                        'audio' => [
                            'input' => [
                                'transcription' => [
                                    'model' => (string) config('supportflow.dictation.model'),
                                    'prompt' => 'Customer support for Harbor Outfitters outdoor retail. Speakers may mention returns, prepaid labels, warranties, and order numbers.',
                                    'keywords' => ['Harbor Outfitters', 'prepaid label', 'return box', 'warranty'],
                                    'languages' => ['en'],
                                ],
                            ],
                        ],
                    ],
                ]);

            $response->throw();
        } catch (RequestException $exception) {
            $this->recorder->fail($run, 'Dictation session could not be created.');

            throw new RuntimeException('Dictation is unavailable right now.', previous: $exception);
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];
        $secret = $this->secretFrom($payload);

        if ($secret === null || str_starts_with($secret, 'sk-')) {
            $this->recorder->fail($run, 'Dictation session response was invalid.');

            throw new RuntimeException('Dictation is unavailable right now.');
        }

        $this->recorder->complete($run, null, [
            'kind' => 'realtime_transcription',
            'model' => config('supportflow.dictation.model'),
        ]);

        return [
            'client_secret' => $secret,
            'expires_at' => isset($payload['expires_at']) ? (string) $payload['expires_at'] : null,
            'max_seconds' => (int) config('supportflow.dictation.max_seconds', 120),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function secretFrom(array $payload): ?string
    {
        $value = $payload['value'] ?? data_get($payload, 'client_secret.value');

        return is_string($value) && $value !== '' ? $value : null;
    }
}

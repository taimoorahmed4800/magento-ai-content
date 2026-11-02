<?php
declare(strict_types=1);

namespace Taimoor\AiContent\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Small client for any OpenAI-compatible chat API.
 * Sends a system + user message and returns the JSON object the AI answered with.
 */
class LlmClient
{
    private const MAX_ATTEMPTS = 3;
    private const RETRY_STATUSES = [429, 500, 502, 503, 504];

    public function __construct(
        private readonly Config $config,
        private readonly CurlFactory $curlFactory,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function chatJson(string $system, string $user): array
    {
        if (!$this->config->isEnabled()) {
            throw new LocalizedException(__('AI Content Generator is disabled in the configuration.'));
        }

        $baseUrl = $this->config->getBaseUrl();
        $model   = $this->config->getModel();

        if ($baseUrl === '' || $model === '') {
            throw new LocalizedException(__('Please set the API Base URL and the Model in the configuration.'));
        }

        $body = $this->json->serialize([
            'model'       => $model,
            'temperature' => 0.4,
            'max_tokens'  => 2500,
            'messages'    => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
        ]);

        $response = $this->post($baseUrl . '/chat/completions', $body);

        try {
            $data = $this->json->unserialize($response);
        } catch (\InvalidArgumentException $e) {
            throw new LocalizedException(__('The AI API returned an invalid response.'));
        }

        $text = $data['choices'][0]['message']['content'] ?? null;

        if (!is_string($text) || trim($text) === '') {
            throw new LocalizedException(__('The AI API returned an empty answer.'));
        }

        return $this->parseJsonAnswer($text);
    }

    /**
     * POST with a few retries for temporary errors (rate limit, server busy).
     */
    private function post(string $url, string $body): string
    {
        $lastStatus = 0;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $curl = $this->curlFactory->create();
                $curl->setTimeout(60);
                $curl->addHeader('Content-Type', 'application/json');

                if (($key = $this->config->getApiKey()) !== '') {
                    $curl->addHeader('Authorization', 'Bearer ' . $key);
                }

                $curl->post($url, $body);
                $lastStatus = (int) $curl->getStatus();

                if ($lastStatus >= 200 && $lastStatus < 300) {
                    return (string) $curl->getBody();
                }

                if (!in_array($lastStatus, self::RETRY_STATUSES, true)) {
                    break; // a real error (401, 404...): retrying will not help
                }
            } catch (\Exception $e) {
                // network problem: try again
                $this->logger->warning('AiContent: connection problem: ' . $e->getMessage());
            }

            if ($attempt < self::MAX_ATTEMPTS) {
                sleep(2);
            }
        }

        // Never log the API key or the full request
        $this->logger->error('AiContent: AI API failed with HTTP status ' . $lastStatus);

        throw new LocalizedException(__('The AI API request failed (HTTP status %1). Please try again later.', $lastStatus));
    }

    /**
     * The model should answer with only JSON, but sometimes adds ```json fences or extra text.
     *
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    private function parseJsonAnswer(string $text): array
    {
        $start = strpos($text, '{');
        $end   = strrpos($text, '}');

        if ($start === false || $end === false || $end <= $start) {
            throw new LocalizedException(__('The AI did not return JSON.'));
        }

        try {
            $data = $this->json->unserialize(substr($text, $start, $end - $start + 1));
        } catch (\InvalidArgumentException $e) {
            throw new LocalizedException(__('The AI returned JSON that could not be read.'));
        }

        if (!is_array($data)) {
            throw new LocalizedException(__('The AI returned an unexpected format.'));
        }

        return $data;
    }
}

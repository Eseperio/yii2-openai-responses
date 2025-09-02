<?php

namespace eseperio\openai\responses;

use eseperio\openai\responses\enums\OpenAiModel;
use eseperio\openai\responses\models\AskRequest;
use InvalidArgumentException;
use OpenAI;
use Yii;
use yii\base\Component;
// Add AiRequest model import
use app\modules\ai\models\AiRequest;
use yii\web\Application;

class OpenAiComponent extends Component
{
    /**
     * API key used to authenticate requests.
     *
     * @var string
     */
    private string $apiKey;

    /**
     * Default model used for requests.
     */
    public OpenAiModel|string $model = OpenAiModel::GPT_5;

    /**
     * Default tools configuration.
     *
     * @var array
     */
    public array $tools = [];

    /**
     * Default metadata attached to requests.
     *
     * @var array
     */
    public array $metadata = [];

    /**
     * Default instructions sent to the API.
     *
     * @var string|null
     */
    private ?string $instructions = null;

    /**
     * Policy applied to default instructions.
     *
     * @var string
     */
    public string $instructionsPolicy = self::INSTRUCTIONS_OPTIONAL;

    public const INSTRUCTIONS_REQUIRED = 'required';
    public const INSTRUCTIONS_COMPLEMENTARY = 'complementary';
    public const INSTRUCTIONS_OPTIONAL = 'optional';

    /**
     * @var object|null
     */
    private $client;

    /**
     * @var object|null
     */
    protected $lastResponse;

    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        parent::init();
        if ($this->client === null) {
            $this->client = OpenAI::client($this->apiKey);
        }
    }

    /**
     * Sets the API key.
     */
    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    /**
     * Sets default instructions.
     */
    public function setInstructions(?string $instructions): void
    {
        $this->instructions = $instructions;
    }

    /**
     * Creates a model containing default ask request configuration.
     */
    public function createAskRequest(): AskRequest
    {
        $model = new AskRequest();
        $model->model = $this->model;
        $model->tools = $this->tools;
        $model->metadata = $this->metadata;
        $model->instructions = $this->instructions;

        return $model;
    }

    /**
     * Sends the prompt to the API and returns the content of the response.
     *
     * @param string|AskRequest $promptOrRequest
     * @param string|null $instructions
     * @param array $metadata
     * @return string
     * @throws \Throwable
     * @throws \Throwable
     */
    public function ask($promptOrRequest, ?string $instructions = null, array $metadata = []): string
    {
        if ($promptOrRequest instanceof AskRequest) {
            $request = $promptOrRequest;
        } else {
            $request = $this->createAskRequest();
            $request->input = $promptOrRequest;
            $request->instructions = $this->resolveInstructions($instructions);
            $request->metadata = array_merge($this->metadata, $metadata);
        }

        if (!$request->validate()) {
            throw new InvalidArgumentException('Invalid ask request: ' . json_encode($request->getErrors()));
        }

        $params = $request->toRequestArray();

        // Compute a deterministic hash of the request payload
        $requestHash = $this->computeRequestHash($params);

        // Try to serve from cache
        if (($cached = $this->findCachedResponse($requestHash)) !== null) {
            $this->lastResponse = $cached; // Keep lastResponse for transparency
            $output = $this->extractOutputText($cached);

            return (string)$output;
        }

        try {
            Yii::debug('Sending request to OpenAI: ' . json_encode($params), __METHOD__);
            $this->lastResponse = $this->client->responses()->create($params);
            $output = $this->extractOutputText($this->lastResponse);

            return (string)$output;
        } catch (\Throwable $e) {
            Yii::error('OpenAI request failed: ' . $e->getMessage(), __METHOD__);
            throw $e;
        }
    }

    /**
     * Returns the last response object.
     */
    public function getLastResponse()
    {
        return $this->lastResponse;
    }

    /**
     * Sets a custom client instance. Mainly used for tests.
     */
    public function setClient($client): void
    {
        $this->client = $client;
    }

    /**
     * Resolves instructions based on the current policy.
     */
    protected function resolveInstructions(?string $userInstructions): ?string
    {
        $default = $this->instructions;

        switch ($this->instructionsPolicy) {
            case self::INSTRUCTIONS_REQUIRED:
                if ($userInstructions !== null && $userInstructions !== '') {
                    throw new InvalidArgumentException('Instructions are enforced and cannot be overridden.');
                }

                return $default;
            case self::INSTRUCTIONS_COMPLEMENTARY:
                if ($default && $userInstructions) {
                    return $default . "\n" . $userInstructions;
                }

                return $default ?? $userInstructions;
            case self::INSTRUCTIONS_OPTIONAL:
            default:
                return $userInstructions ?? $default;
        }
    }

    /**
     * Compute a deterministic hash for the given request payload.
     */
    private function computeRequestHash(array $params): string
    {
        $normalized = $this->normalizePayload($params);
        return hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Recursively sort array keys to produce a stable representation.
     */
    private function normalizePayload($data)
    {
        if (is_array($data)) {
            // Separate associative and numeric arrays to keep list order
            if (array_values($data) === $data) {
                // Indexed array: normalize each item but preserve order
                return array_map([$this, 'normalizePayload'], $data);
            }
            // Associative array: sort keys
            ksort($data);
            foreach ($data as $k => $v) {
                $data[$k] = $this->normalizePayload($v);
            }
            return $data;
        }
        return $data;
    }

    /**
     * Try to find a cached response by its request hash within the TTL window.
     *
     * @return array|null Returns the response_json payload or null.
     */
    private function findCachedResponse(string $requestHash): ?array
    {
        try {
            $ttl = $this->getRequestCacheTtlSeconds();
            if ($ttl <= 0) {
                // Cache disabled by configuration
                return null;
            }

            $minCreatedAt = time() - $ttl;

            $record = AiRequest::find()
                ->where(['request_hash' => $requestHash])
                ->andWhere(['>=', 'created_at', $minCreatedAt])
                ->orderBy(['id' => SORT_DESC])
                ->one();

            if ($record && !empty($record->response_json)) {
                return is_array($record->response_json)
                    ? $record->response_json
                    : json_decode((string)$record->response_json, true);
            }
        } catch (\Throwable $e) {
            Yii::error('Error while reading AiRequest cache: ' . $e->getMessage(), __METHOD__);
        }

        return null;
    }

    /**
     * Extract output text from either an SDK response object or an array payload.
     */
    private function extractOutputText($response): string
    {
        // Normalize SDK object responses to array and reuse array extractor
        if (is_object($response)) {
            if (method_exists($response, 'toArray')) {
                $asArray = $response->toArray();
                return $this->extractTextFromArray($asArray);
            }

            // Legacy object properties fallback
            if (isset($response->outputText) && is_string($response->outputText)) {
                return (string)$response->outputText;
            }
            if (isset($response->output) && is_array($response->output)) {
                $first = $response->output[0] ?? null;
                if (is_object($first) && isset($first->content) && is_array($first->content)) {
                    $firstContent = $first->content[0] ?? null;
                    if (is_object($firstContent)) {
                        if (isset($firstContent->text) && is_string($firstContent->text)) {
                            return (string)$firstContent->text;
                        }
                        if (isset($firstContent->text) && is_object($firstContent->text) && isset($firstContent->text->value)) {
                            return (string)$firstContent->text->value;
                        }
                    }
                }
            }
        }

        if (is_array($response)) {
            return $this->extractTextFromArray($response);
        }

        // Fallback
        return '';
    }

    /**
     * Extract output text from a response array supporting multiple shapes.
     */
    private function extractTextFromArray(array $response): string
    {
        // Direct convenience property
        if (isset($response['outputText']) && is_string($response['outputText'])) {
            return (string)$response['outputText'];
        }
        if (isset($response['output_text']) && is_string($response['output_text'])) {
            return (string)$response['output_text'];
        }

        // Responses API typical shape: output[0].content[*].text
        if (isset($response['output'][0]['content']) && is_array($response['output'][0]['content'])) {
            foreach ($response['output'][0]['content'] as $content) {
                // text as string
                if (is_array($content) && isset($content['text']) && is_string($content['text'])) {
                    return (string)$content['text'];
                }
                // text as object with value key
                if (is_array($content) && isset($content['text']) && is_array($content['text']) && isset($content['text']['value'])) {
                    return (string)$content['text']['value'];
                }
                // message.content (OpenAI chat-like)
                if (is_array($content) && isset($content['message']['content']) && is_string($content['message']['content'])) {
                    return (string)$content['message']['content'];
                }
            }
        }

        // Common chat completion shapes
        if (isset($response['choices'][0]['message']['content']) && is_string($response['choices'][0]['message']['content'])) {
            return (string)$response['choices'][0]['message']['content'];
        }
        if (isset($response['choices'][0]['text']) && is_string($response['choices'][0]['text'])) {
            return (string)$response['choices'][0]['text'];
        }

        // Another possible top-level content shape
        if (isset($response['content'][0]['text']) && is_string($response['content'][0]['text'])) {
            return (string)$response['content'][0]['text'];
        }
        if (isset($response['content'][0]['text']['value']) && is_string($response['content'][0]['text']['value'])) {
            return (string)$response['content'][0]['text']['value'];
        }

        return '';
    }

    /**
     * Get request cache TTL (in seconds) from the AI module. Defaults to 24h (86400s).
     */
    private function getRequestCacheTtlSeconds(): int
    {
        $default = 86400;
        try {
            $module = Yii::$app->getModule('ai');
            if ($module !== null && property_exists($module, 'requestCacheTtlSeconds')) {
                $value = $module->requestCacheTtlSeconds;
                if (is_numeric($value)) {
                    return max(0, (int)$value);
                }
            }
        } catch (\Throwable $e) {
            Yii::warning('Unable to read requestCacheTtlSeconds from module: ' . $e->getMessage(), __METHOD__);
        }

        return $default;
    }
}

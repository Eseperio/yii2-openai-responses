<?php

namespace eseperio\openai\responses;

use eseperio\openai\responses\models\AskRequest;
use eseperio\openai\responses\models\OpenAiModel;
use InvalidArgumentException;
use OpenAI;
use Yii;
use yii\base\Component;

/**
 * Component wrapping the OpenAI responses API.
 */
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
            throw new InvalidArgumentException('Invalid ask request: '.json_encode($request->getErrors()));
        }

        $params = $request->toRequestArray();

        try {
            Yii::debug('Sending request to OpenAI: '.json_encode($params), __METHOD__);
            $this->lastResponse = $this->client->responses()->create($params);
            $output = $this->lastResponse->outputText ?? ($this->lastResponse->output[0]->content[0]->text ?? '');

            return (string) $output;
        } catch (\Throwable $e) {
            Yii::error('OpenAI request failed: '.$e->getMessage(), __METHOD__);
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
                    return $default."\n".$userInstructions;
                }

                return $default ?? $userInstructions;
            case self::INSTRUCTIONS_OPTIONAL:
            default:
                return $userInstructions ?? $default;
        }
    }
}


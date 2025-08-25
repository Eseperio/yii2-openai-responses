<?php

namespace eseperio\openai\responses\models;

use eseperio\openai\responses\enums\OpenAiModel;
use eseperio\openai\responses\enums\ResponseFormats;
use OpenAI\Responses\Responses\Format\JsonObjectFormat;
use OpenAI\Responses\Responses\Format\TextFormat;
use yii\base\Model;

/**
 * Model representing a request to the OpenAI responses API.
 */
class AskRequest extends Model
{
    /**
     * Target model.
     */
    public OpenAiModel|string $model;

    /**
     * The input prompt.
     *
     * @var string
     */
    public string $input = "";

    /**
     * Instructions for the model.
     *
     * @var string|null
     */
    public ?string $instructions = null;

    /**
     * the class name of the supported format for the response.
     *
     * @var ResponseFormats
     */
    public ResponseFormats $response_format = ResponseFormats::TEXT;

    /**
     * Tools configuration.
     *
     * @var array
     */
    public array $tools = [];

    /**
     * Metadata to attach to the request.
     *
     * @var array
     */
    public array $metadata = [];

    /**
     * Optional previous response ID to continue a conversation.
     *
     * @var string|null
     */
    public ?string $previous_response_id = null;

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['model', 'input'], 'required'],
            ['model', 'validateModel'],
            [['input', 'instructions', 'previous_response_id'], 'string'],
            [['tools', 'metadata'], 'validateArray'],
            ['response_format', 'in',
                'range' => ResponseFormats::cases(),
                'message' => 'Invalid response format selected.'
            ],

        ];
    }

    /**
     * Validates that the model is one of the allowed OpenAI models.
     */
    public function validateModel(): void
    {
        if ($this->model instanceof OpenAiModel) {
            return;
        }

        $valid = array_map(static fn(OpenAiModel $m) => $m->value, OpenAiModel::cases());
        if (!in_array($this->model, $valid, true)) {
            $error = 'Invalid OpenAI model selected.';
            if (YII_ENV_DEV || YII_ENV_TEST) {
                $error .= " Model selected was: {$this->model}. Valid options are: " . implode(", ", $valid) . ".";
            }
            $this->addError('model', $error);
        }
    }

    /**
     * Validates that the attribute is an array.
     */
    public function validateArray(string $attribute): void
    {
        if (!is_array($this->$attribute)) {
            $this->addError($attribute, ucfirst($attribute) . ' must be an array.');
        }
    }

    /**
     * Converts the model into an array to be sent to the API.
     */
    public function toRequestArray(): array
    {
        $model = $this->model instanceof OpenAiModel ? $this->model->value : $this->model;

        $data = [
            'model' => $model,
            'input' => $this->input,
            'text' => [
                'format'=> $this->response_format->makeFormat()
            ],
        ];

        if ($this->instructions !== null) {
            $data['instructions'] = $this->instructions;
        }
        if (!empty($this->tools)) {
            $data['tools'] = $this->tools;
        }
        if (!empty($this->metadata)) {
            $data['metadata'] = $this->metadata;
        }
        if ($this->previous_response_id !== null) {
            $data['previous_response_id'] = $this->previous_response_id;
        }

        return $data;
    }
}

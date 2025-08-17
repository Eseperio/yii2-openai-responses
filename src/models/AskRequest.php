<?php

namespace eseperio\openai\responses\models;

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
    public string $input;

    /**
     * Instructions for the model.
     *
     * @var string|null
     */
    public ?string $instructions = null;

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
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['model', 'input'], 'required'],
            ['model', 'validateModel'],
            [['input', 'instructions'], 'string'],
            [['tools', 'metadata'], 'validateArray'],
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
            $this->addError('model', 'Invalid model.');
        }
    }

    /**
     * Validates that the attribute is an array.
     */
    public function validateArray(string $attribute): void
    {
        if (!is_array($this->$attribute)) {
            $this->addError($attribute, ucfirst($attribute).' must be an array.');
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

        return $data;
    }
}


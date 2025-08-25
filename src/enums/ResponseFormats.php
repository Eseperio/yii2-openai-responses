<?php

namespace eseperio\openai\responses\enums;

use OpenAI\Responses\Responses\Format\JsonObjectFormat;
use OpenAI\Responses\Responses\Format\JsonSchemaFormat;
use OpenAI\Responses\Responses\Format\TextFormat;

enum ResponseFormats: string
{
    case TEXT = 'text';
    case JSON_OBJECT = 'json_object';
    case JSON_SCHEMA = 'json_schema';

    public function makeFormat(): TextFormat|JsonObjectFormat|JsonSchemaFormat
    {
        return match ($this) {
            self::TEXT => TextFormat::from(['type' => self::TEXT->value]),
            self::JSON_OBJECT => JsonObjectFormat::from(['type' => self::JSON_OBJECT->value]),
            self::JSON_SCHEMA => JsonSchemaFormat::from(['type' => self::JSON_SCHEMA->value]),
        };
    }
}

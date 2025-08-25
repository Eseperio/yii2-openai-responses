<?php
namespace eseperio\openai\responses\models;

enum OpenAiModel: string
{
    case GPT_4_1 = 'gpt-4.1';
    case GPT_4_1_MINI = 'gpt-4.1-mini';
    case GPT_4O = 'gpt-4o';
    case GPT_4O_MINI = 'gpt-4o-mini';
    case GPT_5 = 'gpt-5-chat-latest';
    case GPT_5_MINI= 'gpt-5-mini';
    case GPT_5_NANO = 'gpt-5-nano';
}

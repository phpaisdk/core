<?php

declare(strict_types=1);

namespace AiSdk;

/**
 * Portable capability identifiers. Cases are added as feature slices land;
 * a model must never advertise a capability it cannot honor.
 */
enum Capability
{
    case TextGeneration;
    case Streaming;
    case ToolCalling;
    case StructuredOutput;
    case Reasoning;
    case ImageGeneration;
    case SpeechGeneration;
    case Embedding;
    case TextInput;
    case ImageInput;
    case AudioInput;
    case FileInput;

    public static function fromName(string $name): ?self
    {
        return match ($name) {
            'text_generation' => self::TextGeneration,
            'streaming' => self::Streaming,
            'tool_calling' => self::ToolCalling,
            'structured_output' => self::StructuredOutput,
            'reasoning' => self::Reasoning,
            'image_generation' => self::ImageGeneration,
            'speech_generation', 'text_to_speech' => self::SpeechGeneration,
            'embedding', 'embeddings' => self::Embedding,
            'text_input' => self::TextInput,
            'image_input', 'vision' => self::ImageInput,
            'audio_input' => self::AudioInput,
            'file_input' => self::FileInput,
            default => null,
        };
    }

    public function name(): string
    {
        return match ($this) {
            self::TextGeneration => 'text_generation',
            self::Streaming => 'streaming',
            self::ToolCalling => 'tool_calling',
            self::StructuredOutput => 'structured_output',
            self::Reasoning => 'reasoning',
            self::ImageGeneration => 'image_generation',
            self::SpeechGeneration => 'speech_generation',
            self::Embedding => 'embedding',
            self::TextInput => 'text_input',
            self::ImageInput => 'image_input',
            self::AudioInput => 'audio_input',
            self::FileInput => 'file_input',
        };
    }
}

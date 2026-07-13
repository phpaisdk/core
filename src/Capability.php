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
    case Transcription;
    case Embedding;
    case VideoGeneration;
    case TextInput;
    case ImageInput;
    case AudioInput;
    case VideoInput;
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
            'transcription', 'speech_to_text', 'stt' => self::Transcription,
            'embedding', 'embeddings' => self::Embedding,
            'video_generation' => self::VideoGeneration,
            'text_input' => self::TextInput,
            'image_input', 'vision' => self::ImageInput,
            'audio_input' => self::AudioInput,
            'video_input' => self::VideoInput,
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
            self::Transcription => 'transcription',
            self::Embedding => 'embedding',
            self::VideoGeneration => 'video_generation',
            self::TextInput => 'text_input',
            self::ImageInput => 'image_input',
            self::AudioInput => 'audio_input',
            self::VideoInput => 'video_input',
            self::FileInput => 'file_input',
        };
    }
}

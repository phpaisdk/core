<?php

declare(strict_types=1);

namespace AiSdk\Support\Concerns;

use AiSdk\Exceptions\InvalidArgumentException;
use AiSdk\Message;

trait HasMessages
{
    /** @var array<int, Message> */
    protected array $messages = [];

    protected ?string $instructions = null;

    public function instructions(string $instructions): static
    {
        $this->instructions = $instructions;

        return $this;
    }

    public function prompt(string $prompt): static
    {
        $this->messages[] = Message::user($prompt);

        return $this;
    }

    /**
     * @param  array<int, Message>  $messages
     */
    public function messages(array $messages): static
    {
        foreach ($messages as $message) {
            if (! $message instanceof Message) {
                throw new InvalidArgumentException('messages() expects Message instances.');
            }
        }

        $this->messages = array_values($messages);

        return $this;
    }
}

<?php

namespace App\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Stringable;

/** @implements Arrayable<string, string> */
final class BrazilianDocument implements Arrayable, JsonSerializable, Stringable
{
    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function from(string $document): self
    {
        $digits = preg_replace('/\D+/', '', $document) ?? '';

        return new self($digits);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function formatted(): string
    {
        if (strlen($this->value) !== 11) {
            return $this->value;
        }

        return sprintf(
            '%s.%s.%s-%s',
            substr($this->value, 0, 3),
            substr($this->value, 3, 3),
            substr($this->value, 6, 3),
            substr($this->value, 9, 2)
        );
    }

    public function equals(BrazilianDocument $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * @return array{value: string, formatted: string}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value(),
            'formatted' => $this->formatted(),
        ];
    }

    public function jsonSerialize(): string
    {
        return $this->value();
    }

    public function __toString(): string
    {
        return $this->value();
    }
}

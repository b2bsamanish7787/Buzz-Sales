<?php
class Validator {
    private array $errors = [];

    public function required(mixed $value, string $field): self {
        if ($value === null || trim((string)$value) === '') {
            $this->errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
        }
        return $this;
    }

    public function email(mixed $value, string $field = 'email'): self {
        if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = 'Please enter a valid email address.';
        }
        return $this;
    }

    public function minLength(mixed $value, int $min, string $field): self {
        if (strlen((string)$value) < $min) {
            $this->errors[$field] = ucfirst(str_replace('_', ' ', $field)) . " must be at least {$min} characters.";
        }
        return $this;
    }

    public function maxLength(mixed $value, int $max, string $field): self {
        if (strlen((string)$value) > $max) {
            $this->errors[$field] = ucfirst(str_replace('_', ' ', $field)) . " must not exceed {$max} characters.";
        }
        return $this;
    }

    public function numeric(mixed $value, string $field): self {
        if ($value !== '' && !is_numeric($value)) {
            $this->errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' must be a number.';
        }
        return $this;
    }

    public function sanitize(mixed $value): string {
        return htmlspecialchars(strip_tags(trim((string)$value)), ENT_QUOTES, 'UTF-8');
    }

    public function hasErrors(): bool {
        return !empty($this->errors);
    }

    public function getErrors(): array {
        return $this->errors;
    }

    public function firstError(): string {
        return array_values($this->errors)[0] ?? '';
    }
}

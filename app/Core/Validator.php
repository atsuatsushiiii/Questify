<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Core;

/**
 * Small rule-string validator. It returns the first error per field and a
 * cleaned payload containing only fields that declared rules.
 */
final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    /** @var array<string, mixed> */
    private array $clean = [];

    /** @var array<string, bool> */
    private array $failed = [];

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $rules
     * @param array<string, string> $labels
     */
    public function __construct(
        private readonly array $data,
        private readonly array $rules,
        private readonly array $labels = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $rules
     * @param array<string, string> $labels
     */
    public static function make(array $data, array $rules, array $labels = []): self
    {
        return new self($data, $rules, $labels);
    }

    public function validate(): bool
    {
        foreach ($this->rules as $field => $ruleString) {
            $raw = $this->data[$field] ?? null;
            $label = $this->label($field);
            $rules = explode('|', $ruleString);
            $isRequired = in_array('required', $rules, true);

            if (is_array($raw)) {
                $this->fail($field, $label . ' is not valid.');
                continue;
            }

            $value = $raw;
            $isEmpty = $raw === null || (is_string($value) && trim($value) === '');

            if ($isEmpty) {
                if ($isRequired) {
                    $this->fail($field, $label . ' is required.');
                } else {
                    $this->clean[$field] = null;
                }

                continue;
            }

            if (!is_string($value) && !is_int($value) && !is_float($value)) {
                $this->fail($field, $label . ' is not valid.');
                continue;
            }

            $value = is_string($value) ? $value : (string) $value;

            foreach ($rules as $rule) {
                if ($rule === 'required' || $rule === '') {
                    continue;
                }

                [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);

                if (!$this->check($field, $value, $name, $parameter, $label)) {
                    continue 2;
                }
            }

            $this->clean[$field] = $value;
        }

        return $this->errors === [];
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        return $this->errors === [] ? null : (string) reset($this->errors);
    }

    /** @return array<string, mixed> */
    public function validated(): array
    {
        return $this->clean;
    }

    private function check(string $field, string $value, string $rule, ?string $parameter, string $label): bool
    {
        return match ($rule) {
            'string' => true,
            'email' => $this->checkEmail($field, $value, $label),
            'min' => $this->checkLength($field, $value, (int) $parameter, true, $label),
            'max' => $this->checkLength($field, $value, (int) $parameter, false, $label),
            'username' => $this->checkUsername($field, $value, $label),
            'password' => $this->checkPassword($field, $value, $label),
            'confirmed' => $this->checkConfirmed($field, $value, $label),
            'same' => $this->checkSame($field, $value, (string) $parameter, $label),
            'accepted' => $this->checkAccepted($field, $value, $label),
            'in' => $this->checkIn($field, $value, (string) $parameter, $label),
            'timezone' => $this->checkTimezone($field, $value, $label),
            'hex' => $this->checkHex($field, $value, (int) ($parameter ?? 0), $label),
            default => true,
        };
    }

    private function checkEmail(string $field, string $value, string $label): bool
    {
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false || mb_strlen($value) > 190) {
            return $this->fail($field, $label . ' must be a valid email address.');
        }

        return true;
    }

    private function checkLength(string $field, string $value, int $limit, bool $minimum, string $label): bool
    {
        $length = mb_strlen($value);

        if ($minimum && $length < $limit) {
            return $this->fail($field, sprintf('%s must be at least %d characters.', $label, $limit));
        }

        if (!$minimum && $length > $limit) {
            return $this->fail($field, sprintf('%s must be %d characters or fewer.', $label, $limit));
        }

        return true;
    }

    private function checkUsername(string $field, string $value, string $label): bool
    {
        if (preg_match('/^[A-Za-z0-9_]{3,30}$/', $value) !== 1) {
            return $this->fail(
                $field,
                $label . ' must be 3–30 characters using letters, numbers, or underscores.',
            );
        }

        return true;
    }

    private function checkPassword(string $field, string $value, string $label): bool
    {
        if (preg_match('/[A-Za-z]/', $value) !== 1 || preg_match('/\d/', $value) !== 1) {
            return $this->fail($field, $label . ' must include at least one letter and one number.');
        }

        return true;
    }

    private function checkConfirmed(string $field, string $value, string $label): bool
    {
        return $this->checkSame($field, $value, $field . '_confirmation', $label);
    }

    private function checkSame(string $field, string $value, string $otherField, string $label): bool
    {
        $other = $this->data[$otherField] ?? null;

        if (!is_string($other) || !hash_equals($other, $value)) {
            return $this->fail($field, $label . ' does not match.');
        }

        return true;
    }

    private function checkAccepted(string $field, string $value, string $label): bool
    {
        if (!in_array(strtolower($value), ['1', 'on', 'yes', 'true'], true)) {
            return $this->fail($field, $label . ' must be accepted.');
        }

        return true;
    }

    private function checkIn(string $field, string $value, string $parameter, string $label): bool
    {
        $allowed = explode(',', $parameter);

        if (!in_array($value, $allowed, true)) {
            return $this->fail($field, $label . ' is not valid.');
        }

        return true;
    }

    private function checkTimezone(string $field, string $value, string $label): bool
    {
        if (!in_array($value, timezone_identifiers_list(), true)) {
            return $this->fail($field, $label . ' is not a recognised timezone.');
        }

        return true;
    }

    private function checkHex(string $field, string $value, int $length, string $label): bool
    {
        if (($length > 0 && strlen($value) !== $length) || !ctype_xdigit($value)) {
            return $this->fail($field, $label . ' is not valid.');
        }

        return true;
    }

    private function fail(string $field, string $message): bool
    {
        if (!isset($this->failed[$field])) {
            $this->errors[$field] = $message;
            $this->failed[$field] = true;
        }

        return false;
    }

    private function label(string $field): string
    {
        return $this->labels[$field] ?? ucwords(str_replace(['_', '-'], ' ', $field));
    }
}

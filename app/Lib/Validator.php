<?php
final class Validator {
  public array $errors = [];

  public function required(string $field, $value, string $label): void {
    if ($value === null || $value === '' ) $this->errors[$field] = "$label είναι υποχρεωτικό.";
  }
  public function email(string $field, ?string $value, string $label): void {
    if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) $this->errors[$field] = "Μη έγκυρο email.";
  }
  public function date(string $field, ?string $value, string $label): void {
    if ($value && !\DateTime::createFromFormat('Y-m-d', $value)) $this->errors[$field] = "Μη έγκυρη ημερομηνία.";
  }
  public function ok(): bool { return empty($this->errors); }
}

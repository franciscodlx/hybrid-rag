<?php

declare(strict_types=1);

namespace HybridRag\Infrastructure\Persistence\Database;

/**
 * Lectura tipada de filas del query builder (stdClass). Falla de forma
 * explícita si el esquema no coincide con lo esperado.
 */
final readonly class Row
{
    /** @var array<string, mixed> */
    private array $values;

    public function __construct(object $row)
    {
        $this->values = get_object_vars($row);
    }

    public function string(string $column): string
    {
        $value = $this->value($column);

        return is_string($value) || is_int($value) || is_float($value)
            ? (string) $value
            : throw new \UnexpectedValueException("La columna {$column} no es texto.");
    }

    public function int(string $column): int
    {
        $value = $this->value($column);

        return is_numeric($value) ? (int) $value : throw new \UnexpectedValueException("La columna {$column} no es entera.");
    }

    public function nullableInt(string $column): ?int
    {
        return $this->value($column) === null ? null : $this->int($column);
    }

    public function float(string $column): float
    {
        $value = $this->value($column);

        return is_numeric($value) ? (float) $value : throw new \UnexpectedValueException("La columna {$column} no es numérica.");
    }

    private function value(string $column): mixed
    {
        return array_key_exists($column, $this->values)
            ? $this->values[$column]
            : throw new \UnexpectedValueException("Falta la columna {$column}.");
    }
}

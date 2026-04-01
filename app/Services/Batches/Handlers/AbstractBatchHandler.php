<?php

namespace App\Services\Batches\Handlers;

use App\Models\Batch;
use App\Models\Request as RequestModel;
use App\Models\RequestAttachment;
use App\Services\Batches\Contracts\BatchTypeHandler;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

abstract class AbstractBatchHandler implements BatchTypeHandler
{
    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $aliases
     */
    protected function value(array $row, array $aliases, mixed $default = null): mixed
    {
        foreach ($aliases as $alias) {
            if (array_key_exists($alias, $row)) {
                return $row[$alias];
            }
        }

        return $default;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $rules
     */
    protected function validateRow(array $row, array $rules, array $messages = []): array
    {
        $validator = Validator::make($row, $rules, $messages);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return $validator->validated();
    }

    protected function boolFromMixed(mixed $value, bool $default = false): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = mb_strtolower((string) $value);
        return in_array($normalized, ['1', 'true', 'si', 'sí', 'yes'], true);
    }

    protected function floatFromMixed(mixed $value, float $default = 0): float
    {
        if ($value === null || $value === '') {
            return $default;
        }

        $normalized = str_replace([',', ' '], ['', ''], (string) $value);
        return (float) $normalized;
    }

    protected function dateFromMixed(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $stringValue = (string) $value;

        // Intentar parsear como DD/MM/YYYY
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $stringValue, $matches)) {
            $day = (int) $matches[1];
            $month = (int) $matches[2];
            $year = (int) $matches[3];

            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        // Si ya viene en YYYY-MM-DD, devolverlo
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $stringValue)) {
            return $stringValue;
        }

        // Intentar otros formatos comunes
        $timestamp = strtotime($stringValue);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $file
     */
    protected function createAttachment(RequestModel $request, array $file): void
    {
        RequestAttachment::create([
            'requestId' => $request->id,
            'fileName' => (string) ($file['originalName'] ?? ''),
            'fileSize' => (int) ($file['size'] ?? 0),
            'filePath' => (string) ($file['storedPath'] ?? ''),
            'fileExtension' => (string) ($file['extension'] ?? ''),
        ]);
    }

    abstract public function process(array $row, Batch $batch): ?int;
}

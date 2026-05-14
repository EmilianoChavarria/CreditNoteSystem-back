<?php

namespace App\Http\Requests\Batches;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class StoreBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'batchType' => [
                'required',
                'string',
                Rule::in([
                    'sapScreen',
                    'creditsData',
                    'orderNumbers',
                    'newRequest',
                    'uploadSupport',
                    'users',
                ]),
            ],
            'requestTypeId' => ['nullable', 'integer', 'exists:requesttype,id'],
            'minRange' => ['nullable', 'integer', 'min:0'],
            'maxRange' => ['nullable', 'integer', 'min:0'],
            'file' => ['required'],
            'file.*' => ['file'],
            'welcomeEmailMode' => ['nullable', 'string', Rule::in(['none', 'individual', 'single'])],
            'userWelcomeEmailMode' => ['nullable', 'string', Rule::in(['none', 'individual', 'single'])],
            'sendWelcomeEmails' => ['nullable', 'boolean'],
            'welcomeEmailRecipient' => ['nullable', 'email', 'max:150'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $batchType = (string) $this->input('batchType');

            $files = $this->normalizedFiles();

            if (count($files) === 0) {
                $validator->errors()->add('file', 'Debe enviar al menos un archivo.');
                return;
            }

            if ($batchType === 'uploadSupport' && count($files) > 10) {
                $validator->errors()->add('file', 'En uploadSupport solo se permiten hasta 10 archivos por carga.');
            }

            if (!in_array($batchType, ['sapScreen', 'uploadSupport'], true) && count($files) !== 1) {
                $validator->errors()->add('file', 'Este tipo de carga permite únicamente un archivo.');
            }

            if ($batchType === 'sapScreen') {
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'doc', 'docx', 'pdf'];
            } elseif ($batchType === 'uploadSupport') {
                $allowedExtensions = ['pdf', 'png', 'jpg', 'jpeg', 'csv', 'xml', 'xls', 'xlsx', 'txt', 'docx'];
            } else {
                $allowedExtensions = ['csv', 'xml', 'xls', 'xlsx', 'txt', 'docx'];
            }

            foreach ($files as $index => $file) {
                $extension = strtolower($file->getClientOriginalExtension());
                if (!in_array($extension, $allowedExtensions, true)) {
                    $validator->errors()->add("file.$index", "Formato .$extension no permitido para este batchType.");
                }
            }

            if (in_array($batchType, ['uploadSupport', 'newRequest'], true) && !$this->filled('requestTypeId')) {
                $validator->errors()->add('requestTypeId', 'El campo requestTypeId es obligatorio para este batchType.');
            }

            if ($batchType === 'uploadSupport') {
                if (!$this->filled('minRange') || !$this->filled('maxRange')) {
                    $validator->errors()->add('minRange', 'minRange y maxRange son obligatorios en uploadSupport.');
                }

                if ($this->filled('minRange') && $this->filled('maxRange') && (int) $this->input('minRange') > (int) $this->input('maxRange')) {
                    $validator->errors()->add('minRange', 'minRange no puede ser mayor que maxRange.');
                }
            }

            if ($batchType === 'users' && $this->resolvedWelcomeEmailMode() === 'single' && !$this->filled('welcomeEmailRecipient')) {
                $validator->errors()->add('welcomeEmailRecipient', 'El campo welcomeEmailRecipient es obligatorio cuando welcomeEmailMode es single.');
            }
        });
    }

    public function resolvedWelcomeEmailMode(): string
    {
        $mode = (string) ($this->input('welcomeEmailMode') ?? $this->input('userWelcomeEmailMode') ?? 'none');
        $mode = strtolower(trim($mode));

        if (!in_array($mode, ['none', 'individual', 'single'], true)) {
            $mode = 'none';
        }

        if ($mode === 'none' && $this->boolean('sendWelcomeEmails')) {
            return 'individual';
        }

        if ($mode === 'none' && $this->filled('welcomeEmailRecipient')) {
            return 'single';
        }

        return $mode;
    }

    public function welcomeEmailRecipient(): ?string
    {
        $recipient = trim((string) $this->input('welcomeEmailRecipient', ''));

        return $recipient !== '' ? $recipient : null;
    }

    /**
     * @return array<int, UploadedFile>
     */
    public function normalizedFiles(): array
    {
        $files = $this->file('file');

        if ($files instanceof UploadedFile) {
            return [$files];
        }

        return is_array($files) ? array_values(array_filter($files, fn ($file) => $file instanceof UploadedFile)) : [];
    }
}

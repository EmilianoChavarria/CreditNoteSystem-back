<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BatchFinishedMail extends Mailable
{
    use Queueable, SerializesModels;

    public int $batchId;
    public string $batchType;
    public string $status;
    public int $totalRecords;
    public int $processedRecords;
    public int $errorRecords;
    public int $processingRecords;
    public string $fullName;
    private string $mailLocale;

    public function __construct(
        int $batchId,
        string $batchType,
        string $status,
        int $totalRecords,
        int $processedRecords,
        int $errorRecords,
        int $processingRecords,
        string $fullName,
        string $locale = 'es'
    ) {
        $this->batchId = $batchId;
        $this->batchType = $batchType;
        $this->status = $status;
        $this->totalRecords = $totalRecords;
        $this->processedRecords = $processedRecords;
        $this->errorRecords = $errorRecords;
        $this->processingRecords = $processingRecords;
        $this->fullName = $fullName;
        $this->mailLocale = in_array(strtolower(trim($locale)), ['en', 'es'], true)
            ? strtolower(trim($locale))
            : 'es';
    }

    public function build(): self
    {
        app()->setLocale($this->mailLocale);

        $subjectKey = $this->status === 'completed'
            ? 'emails.batch_subject_completed'
            : 'emails.batch_subject_errors';

        return $this->subject(__($subjectKey, ['id' => $this->batchId]))
            ->view('emails.batch_finished');
    }
}

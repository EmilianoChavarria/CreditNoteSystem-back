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

    public function __construct(
        int $batchId,
        string $batchType,
        string $status,
        int $totalRecords,
        int $processedRecords,
        int $errorRecords,
        int $processingRecords,
        string $fullName
    ) {
        $this->batchId = $batchId;
        $this->batchType = $batchType;
        $this->status = $status;
        $this->totalRecords = $totalRecords;
        $this->processedRecords = $processedRecords;
        $this->errorRecords = $errorRecords;
        $this->processingRecords = $processingRecords;
        $this->fullName = $fullName;
    }

    public function build(): self
    {
        $subjectStatus = $this->status === 'completed'
            ? 'completado'
            : 'finalizado con errores';

        return $this->subject("Batch #{$this->batchId} {$subjectStatus}")
            ->view('emails.batch_finished');
    }
}

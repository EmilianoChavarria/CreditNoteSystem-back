<?php

namespace App\Console\Commands;

use App\Mail\PendingApprovalReminderMail;
use App\Models\WorkflowRequestCurrentStep;
use App\Services\EmailSenderService;
use Illuminate\Console\Command;

class SendPendingApprovalReminders extends Command
{
    protected $signature = 'reminders:pending-approvals';
    protected $description = 'Send reminder emails to users with pending approval requests';

    public function __construct(private readonly EmailSenderService $emailSender)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $steps = WorkflowRequestCurrentStep::query()
            ->whereNotNull('assignedUserId')
            ->with([
                'assignedUser',
                'request.requestType',
                'request.classification',
            ])
            ->get();

        $grouped = $steps->groupBy('assignedUserId');

        $sent = 0;

        foreach ($grouped as $steps) {
            $user = $steps->first()->assignedUser;

            if (!$user || empty($user->email)) {
                continue;
            }

            $requests = $steps
                ->filter(fn($step) => $step->request !== null)
                ->map(fn($step) => [
                    'requestNumber' => (string) $step->request->requestNumber,
                    'requestType'   => (string) ($step->request->requestType?->name ?? ''),
                    'classification'=> (string) ($step->request->classification?->name ?? ''),
                ])
                ->values()
                ->toArray();

            if (empty($requests)) {
                continue;
            }

            $locale = (string) ($user->preferredLanguage ?? 'es');

            $this->emailSender->send(
                new PendingApprovalReminderMail((string) $user->fullName, $requests, $locale),
                (string) $user->email
            );

            $sent++;
        }

        $this->info("Reminder emails sent: {$sent}");

        return Command::SUCCESS;
    }
}

<?php

namespace App\Services\Central;

use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DunningService
{
    protected const MAX_RETRY_ATTEMPTS = 5;

    protected const RETRY_DELAY_HOURS = [0, 24, 72, 168, 336]; // immediate, 1 day, 3 days, 7 days, 14 days

    /**
     * Process a failed payment attempt. Returns the next retry schedule.
     *
     * @return array{should_retry: bool, next_attempt_at: ?Carbon, attempt_number: int, max_attempts: int}
     */
    public function handleFailedPayment(Payment $payment): array
    {
        return DB::transaction(function () use ($payment) {
            $invoice = $payment->invoice;
            $data = $payment->data ?? [];
            $failedAttempts = $data['failed_attempts'] ?? 0;
            $attemptNumber = $failedAttempts + 1;

            if ($attemptNumber > self::MAX_RETRY_ATTEMPTS) {
                $this->escalateToManual($invoice);

                return [
                    'should_retry' => false,
                    'next_attempt_at' => null,
                    'attempt_number' => $attemptNumber,
                    'max_attempts' => self::MAX_RETRY_ATTEMPTS,
                ];
            }

            $delayHours = self::RETRY_DELAY_HOURS[$attemptNumber - 1] ?? 336;
            $nextAttempt = Carbon::now()->addHours($delayHours);

            $data = $payment->data ?? [];
            $data['failed_attempts'] = $attemptNumber;
            $data['dunning_level'] = $attemptNumber;
            $data['next_retry_at'] = $nextAttempt->toDateTimeString();
            $payment->data = $data;
            $payment->save();

            if ($invoice) {
                $invoice->update(['status' => 'overdue']);
            }

            return [
                'should_retry' => true,
                'next_attempt_at' => $nextAttempt,
                'attempt_number' => $attemptNumber,
                'max_attempts' => self::MAX_RETRY_ATTEMPTS,
            ];
        });
    }

    /**
     * @return array{invoice: Invoice, payment: Payment, should_retry: bool, next_attempt_at: ?Carbon}
     */
    public function retryPayment(Payment $payment): array
    {
        $invoice = $payment->invoice;

        if (! $invoice || $invoice->paid_at) {
            return [
                'invoice' => $invoice,
                'payment' => $payment,
                'should_retry' => false,
                'next_attempt_at' => null,
            ];
        }

        $paymentData = $payment->data ?? [];
        $failedAttempts = $paymentData['failed_attempts'] ?? 0;
        $attemptNumber = $failedAttempts + 1;

        if ($attemptNumber > self::MAX_RETRY_ATTEMPTS) {
            return [
                'invoice' => $invoice,
                'payment' => $payment,
                'should_retry' => false,
                'next_attempt_at' => null,
            ];
        }

        $delayHours = self::RETRY_DELAY_HOURS[$attemptNumber - 1] ?? 336;
        $nextAttempt = Carbon::now()->addHours($delayHours);

        $data['failed_attempts'] = $attemptNumber;
        $data['dunning_level'] = $attemptNumber;
        $data['last_retry_at'] = Carbon::now()->toDateTimeString();
        $data['next_retry_at'] = $nextAttempt->toDateTimeString();
        $payment->data = $data;
        $payment->save();

        return [
            'invoice' => $invoice,
            'payment' => $payment,
            'should_retry' => $attemptNumber < self::MAX_RETRY_ATTEMPTS,
            'next_attempt_at' => $attemptNumber < self::MAX_RETRY_ATTEMPTS ? $nextAttempt : null,
        ];
    }

    /**
     * @return array{dunning_level: int, sent_at: string}
     */
    public function sendDunningNotification(Invoice $invoice, int $level): array
    {
        $templateMap = [
            1 => 'payment_reminder_1',
            2 => 'payment_reminder_2',
            3 => 'payment_reminder_3',
            4 => 'final_notice',
            5 => 'suspension_notice',
        ];

        $template = $templateMap[$level] ?? 'final_notice';

        $notification = [
            'tenant_id' => $invoice->tenant_id,
            'invoice_id' => $invoice->id,
            'template' => $template,
            'level' => $level,
            'sent_at' => now()->toDateTimeString(),
        ];

        $data = $invoice->data ?? [];
        $data['dunning_notifications'] = $data['dunning_notifications'] ?? [];
        $data['dunning_notifications'][] = $notification;
        $invoice->data = $data;
        $invoice->save();

        return [
            'dunning_level' => $level,
            'sent_at' => $notification['sent_at'],
        ];
    }

    public function escalateToManual(Invoice $invoice): void
    {
        $data = $invoice->data ?? [];
        $data['dunning_escalated'] = true;
        $data['dunning_escalated_at'] = now()->toDateTimeString();

        $invoice->update([
            'status' => 'overdue',
            'data' => $data,
        ]);
    }
}

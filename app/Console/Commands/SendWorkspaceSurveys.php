<?php

namespace App\Console\Commands;

use App\Mail\WorkspaceSurveyMail;
use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Sends "how was your workspace today?" survey emails for bookings whose
 * session ended in the last day and don't have feedback yet. Run once a day
 * (see routes/console.php for the schedule) — safe to run more than once,
 * since a booking that already has feedback or was already sent isn't
 * re-sent (marked via bookings.survey_sent_at).
 */
class SendWorkspaceSurveys extends Command
{
    protected $signature = 'surveys:send';
    protected $description = 'Email a "rate your visit" survey for bookings that ended recently';

    public function handle(): int
    {
        $bookings = Booking::where('status', '!=', 'cancelled')
            ->whereNull('survey_sent_at')
            ->whereBetween('end_datetime', [now()->subDay(), now()])
            ->whereDoesntHave('feedback')
            ->with('customer')
            ->get();

        if ($bookings->isEmpty()) {
            $this->info('No surveys to send right now.');
            return self::SUCCESS;
        }

        foreach ($bookings as $booking) {
            $surveyLink = config('services.frontend_url') . "/feedback/{$booking->id}";

            try {
                Mail::to($booking->customer->email)->send(new WorkspaceSurveyMail($booking, $surveyLink));
                $booking->update(['survey_sent_at' => now()]);
                $this->line("Sent survey for booking #{$booking->id} to {$booking->customer->email}");
            } catch (\Throwable $e) {
                $this->error("Failed to send survey for booking #{$booking->id}: " . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}

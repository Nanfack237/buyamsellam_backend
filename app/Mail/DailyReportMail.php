<?php
namespace App\Mail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;

class DailyReportMail extends Mailable
{
    use Queueable, SerializesModels;
    public array $reportData;
    public Carbon $reportDate;

    public function __construct(array $reportData, Carbon $reportDate)
    {
        $this->reportData = $reportData;
        $this->reportDate = $reportDate;
    }

    public function envelope(): Envelope
    {
        App::setLocale($this->reportData['owner_locale'] ?? config('app.locale'));
        return new Envelope(
            subject: __('reports.subject', [
                'store_name' => $this->reportData['store_name'] ?? 'Unknown Store',
                'date' => $this->reportDate->format('Y-m-d')
            ]),
            from: env('MAIL_FROM_ADDRESS', 'noreply@yourdomain.com'),
        );
    }

    public function content(): Content
    {
        App::setLocale($this->reportData['owner_locale'] ?? config('app.locale'));
        return new Content(
            view: 'emails.daily_report',
            with: ['report' => $this->reportData],
        );
    }

    public function attachments(): array { return []; }
}
<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TeacherEvidenceStatusMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public array $summary,
        public array $missingTasks
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject('Estado de cumplimiento de evidencias ICACIT')
            ->view('emails.teacher-evidence-status');
    }
}

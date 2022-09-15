<?php

namespace yzh52521\mail;

use yzh52521\facade\Mail;

class SendQueuedMailable
{
    /** @var Mailable */
    protected $mailable;

    public function __construct(Mailable $mailable)
    {
        $this->mailable = $mailable;
    }

    public function handle(Mail $mail)
    {
        $mail->sendNow($this->mailable);
    }

    public function failed($e)
    {
        if ( method_exists($this->mailable, 'failed') ) {
            $this->mailable->failed($e);
        }
    }

    public function __clone()
    {
        $this->mailable = clone $this->mailable;
    }
}

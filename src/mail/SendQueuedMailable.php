<?php

namespace yzh52521\mail;

use yzh52521\Mail;

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

    public function __clone()
    {
        $this->mailable = clone $this->mailable;
    }

}

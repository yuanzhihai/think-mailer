<?php

namespace yzh52521\mail;

use yzh52521\Mail;

class SendQueuedMailable
{

    public function __construct(protected Mailable $mailable)
    {
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

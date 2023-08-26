<?php

namespace yzh52521\mail;


use yzh52521\MailManager;

class SendQueuedMailable
{

    use SerializesModels;
    public function __construct(protected Mailable $mailable)
    {
    }

    public function handle(MailManager $mailer)
    {
        $this->mailable->send($mailer);
    }

    /**
     * Call the failed method on the mailable instance.
     *
     * @param \Throwable $e
     * @return void
     */
    public function failed($e)
    {
        if (method_exists($this->mailable, 'failed')) {
            $this->mailable->failed($e);
        }
    }

    public function __clone()
    {
        $this->mailable = clone $this->mailable;
    }

}

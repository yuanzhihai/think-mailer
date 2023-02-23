<?php

namespace yzh52521\mail;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use think\Container;
use think\Queue;
use think\queue\Queueable;
use think\queue\ShouldQueue;

class Mailer
{

    /** @var array 发信人 */
    protected $from;

    public $replyTo = [];

    public $returnPath = [];

    /** @var array 收信人 */
    protected $to = [];

    /** @var array 抄送 */
    protected $cc = [];

    /** @var array 密送 */
    protected $bcc = [];




    public function __construct(protected SymfonyMailer $mailer,protected Queue $queue,protected Container $container)
    {
    }

    /**
     * Set the global from address and name.
     *
     * @param string $address
     * @param string|null $name
     * @return void
     */
    public function alwaysFrom($address, $name = null)
    {
        $this->from = compact('address', 'name');
    }

    /**
     * Set the global reply-to address and name.
     *
     * @param string $address
     * @param string|null $name
     * @return void
     */
    public function alwaysReplyTo($address, $name = null)
    {
        $this->replyTo = compact('address', 'name');
    }

    /**
     * Set the global return path address.
     *
     * @param string $address
     * @return void
     */
    public function alwaysReturnPath($address)
    {
        $this->returnPath = compact('address');
    }

    /**
     * Set the global to address and name.
     *
     * @param string $address
     * @param string|null $name
     * @return void
     */
    public function alwaysTo($address, $name = null)
    {
        $this->to = compact('address', 'name');
    }

    public function from($address, $name = null)
    {
        $this->from = compact('address', 'name');

        return $this;
    }


    public function to($address, $name = null)
    {
        $this->to = compact('address', 'name');

        return $this;
    }

    public function cc($address, $name = null)
    {
        $this->cc = compact('address', 'name');

        return $this;
    }

    public function bcc($address, $name = null)
    {
        $this->bcc = compact('address', 'name');

        return $this;
    }

    /**
     *  预览邮件
     * @param Mailable $mailable
     */
    public function render(Mailable $mailable)
    {
        $message = $this->createMessage($mailable);
        return $message->getSymfonyMessage()->getHtmlBody();
    }


    /**
     * 发送邮件
     * @param Mailable $mailable
     */
    public function send(Mailable $mailable)
    {
        if ( $mailable instanceof ShouldQueue ) {
            $this->queue($mailable);
        } else {
            $this->sendNow($mailable);
        }
    }

    /**
     * 发送邮件(立即发送)
     * @param Mailable $mailable
     */
    public function sendNow(Mailable $mailable)
    {
        $message = $this->createMessage($mailable);
        if ( isset($this->to['address']) ) {
            $message->to($this->to['address'], $this->to['name'], true);
        }

        if ( !empty($this->cc) ) {
            $message->cc($this->cc);
        }
        if ( !empty($this->bcc) ) {
            $message->bcc($this->bcc);
        }
        $this->sendMessage($message);
    }

    /**
     * 推送至队列发送
     * @param Mailable $mailable
     */
    public function queue(Mailable $mailable)
    {
        $job = new SendQueuedMailable($mailable);

        if ( in_array(Queueable::class, class_uses_recursive($mailable)) ) {
            $queue = $this->queue->connection($mailable->connection);
            if ( $mailable->delay > 0 ) {
                $queue->later($mailable->delay, $job, '', $mailable->queue);
            } else {
                $queue->push($job, '', $mailable->queue);
            }
        } else {
            $this->queue->push($job);
        }
    }

    /**
     * 创建Message
     * @param Mailable $mailable
     * @return Message
     */
    protected function createMessage(Mailable $mailable)
    {
        if ( !empty($this->from['address']) ) {
            $mailable->from($this->from['address'], $this->from['name']);
        }

        if ( !empty($this->replyTo['address']) ) {
            $mailable->replyTo($this->replyTo['address'], $this->replyTo['name']);
        }

        if ( !empty($this->returnPath['address']) ) {
            $mailable->returnPath($this->returnPath['address']);
        }

        return $this->container->invokeClass(Message::class, [$mailable]);
    }

    /**
     * 发送Message
     * @param Message $message
     */
    protected function sendMessage($message)
    {
        try {
            $this->mailer->send($message->getSymfonyMessage(), Envelope::create($message->getSymfonyMessage()));
        } catch ( \Exception $e ) {
            throw new \InvalidArgumentException('error mailer: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

}

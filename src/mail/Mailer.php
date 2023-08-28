<?php

namespace yzh52521\mail;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\Transport\TransportInterface;
use think\Container;
use think\Queue;
use think\queue\Queueable;
use think\queue\ShouldQueue;
use think\View;

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

    protected $queue;

    public function __construct(protected View $views, protected TransportInterface $transport, protected Container $container)
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
     * @param string|array $view
     * @param array $data
     * @return string
     */
    public function render($view, array $data = [])
    {
        [$view, $plain, $raw] = $this->parseView($view);

        $data['message'] = $this->createMessage();

        return $this->renderView($view ?: $plain, $data);
    }


    public function raw($text, $callback)
    {
        return $this->send(['raw' => $text], [], $callback);
    }

    /**
     * Send a new message with only a plain part.
     *
     * @param string $view
     * @param array $data
     * @param mixed $callback
     */
    public function plain($view, array $data, $callback)
    {
        return $this->send(['text' => $view], $data, $callback);
    }

    /**
     * 发送邮件
     * @param Mailable|string|array $view
     * @param array $data
     * @param \Closure|null|string $callback
     */
    public function send($view, $data = [], $callback = null)
    {
        if ($view instanceof Mailable) {
            return $this->sendMailable($view);
        }
        $data['mailer'] = $this->transport;

        [$view, $plain, $raw] = $this->parseView($view);

        $data['message'] = $message = $this->createMessage();
        if (!is_null($callback)) {
            $callback($message);
        }
        $this->addContent($message, $view, $plain, $raw, $data);

        if (isset($this->to['address'])) {
            $this->setGlobalToAndRemoveCcAndBcc($message);
        }

        $symfonyMessage = $message->getSymfonyMessage();

        $this->sendSymfonyMessage($symfonyMessage);
    }

    /**
     * @param Mailable $mailable
     * @return void
     */
    public function sendMailable(Mailable $mailable)
    {
        return $mailable instanceof ShouldQueue
            ? $mailable->queue($this->queue)
            : $mailable->send($this);
    }

    /**
     * Set the global "to" address on the given message.
     *
     * @param Message $message
     * @return void
     */
    protected function setGlobalToAndRemoveCcAndBcc($message)
    {
        $message->forgetTo();

        $message->to($this->to['address'], $this->to['name'], true);

        $message->forgetCc();
        $message->forgetBcc();
    }

    /**
     * Queue a new e-mail message for sending.
     *
     * @param Mailable|string|array $view
     * @param string|null $queue
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function queue($view, $queue = null)
    {
        if (!$view instanceof Mailable) {
            throw new InvalidArgumentException('Only mailable may be queued.');
        }

        return $view->queue($this->queue);
    }

    /**
     * Queue a new e-mail message for sending on the given queue.
     *
     * @param string $queue
     * @param Mailable $view
     * @return mixed
     */
    public function onQueue($queue, $view)
    {
        return $this->queue($view, $queue);
    }

    /**
     * Queue a new e-mail message for sending on the given queue.
     *
     * This method didn't match rest of framework's "onQueue" phrasing. Added "onQueue".
     *
     * @param string $queue
     * @param Mailable $view
     * @return mixed
     */
    public function queueOn($queue, $view)
    {
        return $this->onQueue($queue, $view);
    }

    /**
     * Queue a new e-mail message for sending after (n) seconds.
     *
     * @param \DateTimeInterface|\DateInterval|int $delay
     * @param Mailable $view
     * @param string|null $queue
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function later($delay, $view, $queue = null)
    {
        if (!$view instanceof Mailable) {
            throw new InvalidArgumentException('Only mailables may be queued.');
        }

        return $view->later(
            $delay, is_null($queue) ? $this->queue : $queue
        );
    }

    /**
     * Queue a new e-mail message for sending after (n) seconds on the given queue.
     *
     * @param string $queue
     * @param \DateTimeInterface|\DateInterval|int $delay
     * @param Mailable $view
     * @return mixed
     */
    public function laterOn($queue, $delay, $view)
    {
        return $this->later($delay, $view, $queue);
    }

    /**
     * 创建Message
     * @return Message
     */
    protected function createMessage()
    {
        $message = new Message(new Email());

        if (!empty($this->from['address'])) {
            $message->from($this->from['address'], $this->from['name']);
        }

        if (!empty($this->replyTo['address'])) {
            $message->replyTo($this->replyTo['address'], $this->replyTo['name']);
        }

        if (!empty($this->returnPath['address'])) {
            $message->returnPath($this->returnPath['address']);
        }

        return $message;
    }


    /**
     * Parse the given view name or array.
     *
     * @param \Closure|array|string $view
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    protected function parseView($view)
    {
        if (is_string($view) || $view instanceof \Closure) {
            return [$view, null, null];
        }

        // If the given view is an array with numeric keys, we will just assume that
        // both a "pretty" and "plain" view were provided, so we will return this
        // array as is, since it should contain both views with numerical keys.
        if (is_array($view) && isset($view[0])) {
            return [$view[0], $view[1], null];
        }

        // If this view is an array but doesn't contain numeric keys, we will assume
        // the views are being explicitly specified and will extract them via the
        // named keys instead, allowing the developers to use one or the other.
        if (is_array($view)) {
            return [
                $view['html'] ?? null,
                $view['text'] ?? null,
                $view['raw'] ?? null,
            ];
        }

        throw new \InvalidArgumentException('Invalid view.');
    }

    /**
     * Add the content to a given message.
     *
     * @param Message $message
     * @param string $view
     * @param string $plain
     * @param string $raw
     * @param array $data
     * @return void
     */
    protected function addContent($message, $view, $plain, $raw, $data)
    {
        if (isset($view)) {
            $message->html($this->renderView($view, $data) ?: ' ');
        }
        if (isset($plain)) {
            $message->text($this->renderView($plain, $data) ?: ' ');
        }
        if (isset($raw)) {
            $message->text($raw);
        }
    }

    /**
     * Render the given view.
     *
     * @param \Closure|string $view
     * @param array $data
     * @return string
     */
    protected function renderView($view, $data)
    {
        // 处理变量中包含有对元数据嵌入的变量
        foreach ($data as $k => $v) {
            if (str_contains($k, 'cid:')) {
                $data['message']->embedImage($k, $v, $data);
            }
        }
        $view = value($view, $data);
        return $view instanceof Htmlable
            ? $view->toHtml()
            : $this->views->fetch($view, $data);
    }

    /**
     * Get the Symfony Transport instance.
     *
     * @return \Symfony\Component\Mailer\Transport\TransportInterface
     */
    public function getSymfonyTransport()
    {
        return $this->transport;
    }

    /**
     * Set the Symfony Transport instance.
     *
     * @param \Symfony\Component\Mailer\Transport\TransportInterface $transport
     * @return void
     */
    public function setSymfonyTransport(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    /**
     * Send a Symfony Email instance.
     *
     * @param \Symfony\Component\Mime\Email $message
     * @return \Symfony\Component\Mailer\SentMessage|null
     */
    protected function sendSymfonyMessage(Email $message)
    {
        try {
            return $this->transport->send($message, Envelope::create($message));
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('error mailer: ' . $e->getMessage(), $e->getCode(), $e);
        } finally {
            //
        }
    }

    /**
     * @param Queue $queue
     * @return $this
     */
    public function setQueue(Queue $queue)
    {
        $this->queue = $queue;

        return $this;
    }

}

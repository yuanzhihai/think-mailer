<?php

namespace yzh52521\mail;

use Closure;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use think\Collection;
use think\Container;
use think\helper\Str;
use think\Queue;
use yzh52521\MailManager;

/**
 * Class Mailable
 * @package yzh52521\mail
 *
 * @property string $queue
 * @property integer $delay
 * @property string $connection
 */
class Mailable
{
    /** @var array 发信人 */
    public $from = [];

    /** @var array 收信人 */
    public $to = [];

    /** @var array 抄送 */
    public $cc = [];

    /** @var array 密送 */
    public $bcc = [];

    /** @var array 回复人 */
    public $replyTo = [];

    /**
     * The tags for the message.
     * @var array
     */
    public $tags = [];

    public $charset = 'utf-8';

    /** @var string 标题 */
    public $subject;

    /**
     * The HTML to use for the message.
     * @var string
     */
    protected $html;

    /** @var string 邮件内容(富文本) */
    public $view;

    /** @var string 邮件内容(纯文本) */
    public $textView;

    /** @var array 动态数据 */
    public $viewData = [];

    /** @var array 附件(文件名) */
    public $attachments = [];

    /** @var array 附件(数据) */
    public $rawAttachments = [];

    public $callbacks = [];

    /**
     * The metadata for the message.
     *
     * @var array
     */
    protected $metadata = [];


    /**
     * @param MailManager|Mailer $mailer
     * @return void
     */
    public function send($mailer)
    {
        $this->prepareMailableForDelivery();

        return $mailer->send($this->buildView(), $this->buildViewData(), function ($message) {
            $this->buildFrom($message)
                ->buildRecipients($message)
                ->buildSubject($message)
                ->buildTags($message)
                ->buildMetadata($message)
                ->runCallbacks($message)
                ->buildAttachments($message);
        });
    }

    /**
     * @param Queue $queue
     * @return mixed
     */
    public function queue(Queue $queue)
    {
        if (isset($this->delay)) {
            return $this->later($this->delay, $queue);
        }

        $connection = property_exists($this, 'connection') ? $this->connection : null;

        $queueName = property_exists($this, 'queue') ? $this->queue : null;

        return $queue->connection($connection)->pushOn($queueName,$this->newQueuedJob());
    }


    public function later($delay, Queue $queue)
    {
        $connection = property_exists($this, 'connection') ? $this->connection : null;

        $queueName = property_exists($this, 'queue') ? $this->queue : null;

        return $queue->connection($connection)->laterOn(
            $queueName ?: null, $delay, $this->newQueuedJob()
        );
    }

    /**
     * Make the queued mailable job instance.
     *
     * @return mixed
     */
    protected function newQueuedJob()
    {
        return Container::getInstance()->make(SendQueuedMailable::class,['mailable'=>$this]);
    }

    public function render()
    {
        $this->prepareMailableForDelivery();

        return Container::getInstance()->make('mailer')->render( $this->buildView(), $this->buildViewData());
    }

    protected function buildView()
    {
        if (isset($this->html)) {
            return array_filter([
                'html' => $this->html,
                'text' => $this->textView ?? null,
            ]);
        }
        if (isset($this->view, $this->textView)) {
            return [$this->view, $this->textView];
        } elseif (isset($this->textView)) {
            return ['text' => $this->textView];
        }

        return $this->view;
    }
    


    /**
     * 构造数据
     * @return array
     */
    protected function buildViewData()
    {
        $data = $this->viewData;

        foreach ((new ReflectionClass($this))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() !== self::class) {
                $data[$property->getName()] = $property->getValue($this);
            }
        }

        return $data;
    }

    /**
     * 构造发信人
     * @param Message $message
     * @return $this
     */
    protected function buildFrom($message)
    {
        if (!empty($this->from)) {
            $message->from($this->from[0]['address'], $this->from[0]['name']);
        }
        return $this;
    }

    /**
     * 构造收信人
     * @param Message $message
     * @return $this
     */
    protected function buildRecipients($message)
    {
        foreach (['to', 'cc', 'bcc', 'replyTo'] as $type) {
            foreach ($this->{$type} as $recipient) {
                $message->{$type}($recipient['address'], $recipient['name']);
            }
        }

        return $this;
    }

    /**
     * 构造标题
     * @param Message $message
     * @return $this
     */
    protected function buildSubject($message)
    {
        if ($this->subject) {
            $message->subject($this->subject);
        } else {
            $message->subject(Str::title(Str::snake(class_basename($this), ' ')));
        }

        return $this;
    }


    /**
     * 构造附件
     * @param Message $message
     * @return $this
     */
    protected function buildAttachments($message)
    {
        foreach ($this->attachments as $attachment) {
            $message->attach($attachment['file'], $attachment['options']);
        }

        foreach ($this->rawAttachments as $attachment) {
            $message->attachData(
                $attachment['data'], $attachment['name'], $attachment['options']
            );
        }

        return $this;
    }

    /**
     * Add all defined tags to the message.
     * @param Message $message
     * @return $this
     */
    protected function buildTags($message)
    {
        if ($this->tags) {
            foreach ($this->tags as $tag) {
                $message->getHeaders()->add(new TagHeader($tag));
            }
        }

        return $this;
    }


    /**
     * Add all defined metadata to the message.
     *
     * @param Message $message
     * @return $this
     */
    protected function buildMetadata($message)
    {
        if ($this->metadata) {
            foreach ($this->metadata as $key => $value) {
                $message->getHeaders()->add(new MetadataHeader($key, $value));
            }
        }

        return $this;
    }

    /**
     * 执行回调
     *
     * @param Message $message
     * @return $this
     */
    protected function runCallbacks($message)
    {
        foreach ($this->callbacks as $callback) {
            $callback($message->getSymfonyMessage());
        }

        return $this;
    }


    private function prepareMailableForDelivery()
    {
        if (method_exists($this, 'build')) {
            Container::getInstance()->invoke([$this, 'build']);
        }
        $this->ensureHeadersAreHydrated();
        $this->ensureEnvelopeIsHydrated();
        $this->ensureContentIsHydrated();
    }

    /**
     * Ensure the mailable's headers are hydrated from the "headers" method.
     *
     * @return void
     */
    private function ensureHeadersAreHydrated()
    {
        if (!method_exists($this, 'headers')) {
            return;
        }

        $headers = $this->headers();

        $this->withSymfonyMessage(function ($message) use ($headers) {
            if ($headers->messageId) {
                $message->getHeaders()->addIdHeader('Message-Id', $headers->messageId);
            }

            if (count($headers->references) > 0) {
                $message->getHeaders()->addTextHeader('References', $headers->referencesString());
            }

            foreach ($headers->text as $key => $value) {
                $message->getHeaders()->addTextHeader($key, $value);
            }
        });
    }

    /**
     * Ensure the mailable's "envelope" data is hydrated from the "envelope" method.
     *
     * @return void
     */
    private function ensureEnvelopeIsHydrated()
    {
        if (!method_exists($this, 'envelope')) {
            return;
        }

        $envelope = $this->envelope();

        if (isset($envelope->from)) {
            $this->from($envelope->from->address, $envelope->from->name);
        }

        foreach (['to', 'cc', 'bcc', 'replyTo'] as $type) {
            foreach ($envelope->{$type} as $address) {
                $this->{$type}($address->address, $address->name);
            }
        }

        if ($envelope->subject) {
            $this->subject($envelope->subject);
        }

        foreach ($envelope->tags as $tag) {
            $this->tag($tag);
        }

        foreach ($envelope->metadata as $key => $value) {
            $this->metadata($key, $value);
        }

        foreach ($envelope->using as $callback) {
            $this->withSymfonyMessage($callback);
        }
    }

    /**
     * Ensure the mailable's content is hydrated from the "content" method.
     *
     * @return void
     */
    private function ensureContentIsHydrated()
    {
        if (!method_exists($this, 'content')) {
            return;
        }

        $content = $this->content();

        if ($content->view) {
            $this->view($content->view);
        }

        if ($content->html) {
            $this->view($content->html);
        }

        if ($content->text) {
            $this->text($content->text);
        }

        if ($content->htmlString) {
            $this->html($content->htmlString);
        }

        foreach ($content->with as $key => $value) {
            $this->with($key, $value);
        }
    }


    public function withSymfonyMessage($callback)
    {
        $this->callbacks[] = $callback;

        return $this;
    }

    /**
     * 设置发信人
     * @param      $address
     * @param null $name
     * @return Mailable
     */
    public function from($address, $name = null)
    {
        return $this->setAddress($address, $name, 'from');
    }

    /**
     * 设置收信人
     * @param      $address
     * @param null $name
     * @return Mailable
     */
    public function to($address, $name = null)
    {
        return $this->setAddress($address, $name, 'to');
    }

    /**
     * 设置抄送
     * @param      $address
     * @param null $name
     * @return Mailable
     */
    public function cc($address, $name = null)
    {
        return $this->setAddress($address, $name, 'cc');
    }

    /**
     * 设置密送
     * @param      $address
     * @param null $name
     * @return Mailable
     */
    public function bcc($address, $name = null)
    {
        return $this->setAddress($address, $name, 'bcc');
    }

    public function returnPath($address, $name = null)
    {
        return $this->setAddress($address, $name, 'returnPath');
    }

    /**
     * 设置回复人
     * @param      $address
     * @param null $name
     * @return Mailable
     */
    public function replyTo($address, $name = null)
    {
        return $this->setAddress($address, $name, 'replyTo');
    }


    /**
     * 设置地址
     * All recipients are stored internally as [['name' => ?, 'address' => ?]]
     *
     * @param object|array|string $address
     * @param string|null $name
     * @param string $property
     * @return $this
     */
    protected function setAddress($address, $name = null, $property = 'to')
    {
        if (is_object($address) && !$address instanceof Collection) {
            $address = [$address];
        }

        if ($address instanceof Collection || is_array($address)) {
            foreach ($address as $user) {
                $user = $this->parseUser($user);

                $this->{$property}($user->email, $user->name ?? null);
            }
        } else {
            $this->{$property}[] = compact('address', 'name');
        }
        return $this;
    }

    /**
     * 格式化用户
     * @param $user
     * @return object
     */
    protected function parseUser($user)
    {
        if (is_array($user)) {
            return (object)$user;
        } elseif (is_string($user)) {
            return (object)['email' => $user];
        }

        return $user;
    }

    /**
     * 设置标题
     * @param $subject
     * @return $this
     */
    public function subject($subject)
    {
        $this->subject = $subject;

        return $this;
    }

    public function priority($level = 3)
    {
        $this->callbacks[] = function ($message) use ($level) {
            $message->priority($level);
        };

        return $this;
    }

    /**
     * Add a tag header to the message when supported by the underlying transport.
     *
     * @param string $value
     * @return $this
     */
    public function tag($value)
    {
        array_push($this->tags, $value);

        return $this;
    }

    /**
     * Determine if the mailable has the given tag.
     *
     * @param string $value
     * @return bool
     */
    public function hasTag($value)
    {
        return in_array($value, $this->tags) ||
            (method_exists($this, 'envelope') && in_array($value, $this->envelope()->tags));
    }

    /**
     * Add a metadata header to the message when supported by the underlying transport.
     *
     * @param string $key
     * @param string $value
     * @return $this
     */
    public function metadata($key, $value)
    {
        $this->metadata[$key] = $value;

        return $this;
    }

    /**
     * Determine if the mailable has the given metadata.
     *
     * @param string $key
     * @param string $value
     * @return bool
     */
    public function hasMetadata($key, $value)
    {
        return (isset($this->metadata[$key]) && $this->metadata[$key] === $value) ||
            (method_exists($this, 'envelope') && $this->envelope()->hasMetadata($key, $value));
    }

    /**
     * Set the rendered HTML content for the message.
     *
     * @param string $html
     * @return $this
     */
    public function html($html)
    {
        $this->html = $html;

        return $this;
    }

    /**
     * 设置模板
     * @param       $view
     * @param array $data
     * @return $this
     */
    public function view($view, array $data = [])
    {
        $this->view     = $view;
        $this->viewData = array_merge($this->viewData, $data);

        return $this;
    }

    /**
     * 设置文本
     * @param       $textView
     * @param array $data
     * @return $this
     */
    public function text($textView, array $data = [])
    {
        $this->textView = $textView;
        $this->viewData = array_merge($this->viewData, $data);;

        return $this;
    }

    /**
     * 设置数据
     * @param      $key
     * @param null $value
     * @return $this
     */
    public function with($key, $value = null)
    {
        if (is_array($key)) {
            $this->viewData = array_merge($this->viewData, $key);
        } else {
            $this->viewData[$key] = $value;
        }

        return $this;
    }

    /**
     * 设置附件
     * @param       $file
     * @param array $options
     * @return $this
     */
    public function attach($file, array $options = [])
    {
        $this->attachments[] = compact('file', 'options');

        return $this;
    }

    /**
     * 设置附件
     * @param       $data
     * @param       $name
     * @param array $options
     * @return $this
     */
    public function attachData($data, $name, array $options = [])
    {
        $this->rawAttachments[] = compact('data', 'name', 'options');

        return $this;
    }

}

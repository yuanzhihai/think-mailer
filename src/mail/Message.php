<?php

namespace yzh52521\mail;

use Closure;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Address;
use think\App;
use think\helper\Str;
use think\View;
use think\view\driver\Twig;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;
use Twig\TwigFilter;
use yzh52521\mail\twig\TokenParser\Component;
use Symfony\Component\Mime\Email;

/**
 * Class Message
 * @package yzh52521\mail
 * @method html( $body, $charset = null )
 */
class Message
{
    /**
     *
     * @var Email
     */
    protected Email $message;

    /** @var View */
    protected $view;

    /** @var App */
    protected $app;

    public function __construct(Mailable $mailable, View $view, App $app)
    {
        $this->view    = $view;
        $this->app     = $app;
        $this->message = new Email();

        $this->build($mailable);
    }

    protected function build(Mailable $mailable)
    {
        $this->app->invoke([$mailable, 'build']);

        $this->buildContent($mailable)
            ->buildFrom($mailable)
            ->buildRecipients($mailable)
            ->buildTags($mailable)
            ->buildSubject($mailable)
            ->runCallbacks($mailable)
            ->buildAttachments($mailable);
    }

    /**
     * 构造数据
     * @param Mailable $mailable
     * @return array
     */
    protected function buildViewData(Mailable $mailable)
    {
        $data = $mailable->viewData;

        foreach ( ( new ReflectionClass($mailable) )->getProperties(ReflectionProperty::IS_PUBLIC) as $property ) {
            if ( $property->getDeclaringClass()->getName() !== Mailable::class ) {
                $data[$property->getName()] = $property->getValue($mailable);
            }
        }

        $data['message'] = $this;

        return $data;
    }

    /**
     * 添加内容
     * @param Mailable $mailable
     * @return $this
     */
    protected function buildContent(Mailable $mailable)
    {
        $data = $this->buildViewData($mailable);

        if ( isset($mailable->markdown) ) {

            $html = $this->parseDown($mailable->markdown, $data, $mailable->markdownCallback);

            $html = ( new CssToInlineStyles() )->convert($html, file_get_contents(__DIR__ . '/resource/css/default.css'));

            $this->html($html, $mailable->charset);
        } else {
            if ( isset($mailable->view) ) {
                $this->html($this->fetchView($mailable->view, $data), $mailable->charset);
            } elseif ( isset($mailable->textView) ) {
                $method = isset($mailable->view) ? 'html' : 'text';

                $this->$method($this->fetchView($mailable->textView, $data), $mailable->charset);
            }
        }
        return $this;
    }

    /**
     * Add all defined tags to the message.
     * @return $this
     */
    protected function buildTags(Mailable $mailable)
    {
        if ( $mailable->tags ) {
            foreach ( $mailable->tags as $tag ) {
                $this->message->getHeaders()->add(new TagHeader($tag));
            }
        }

        return $this;
    }

    /**
     * 解析markdown
     * @param         $view
     * @param         $data
     * @param Closure $callback
     * @return string
     */
    protected function parseDown($view, $data, Closure $callback = null)
    {
        /** @var Twig $twig */
        $twig = $this->view->engine('twig');

        $parser        = new Markdown();
        $parser->html5 = true;

        $twig->getTwig()->addFilter(new TwigFilter('markdown', function ($content) use ($parser) {
            $content = preg_replace('/^[^\S\n]+/m', '', $content);
            return $parser->parse($content);
        }));

        $twig->getTwig()->addTokenParser(new Component());

        $twig->getLoader()->addPath(__DIR__ . '/resource/view', 'mail');

        if ( $callback ) {
            $callback($twig);
        }

        $content = $twig->getTwig()->render($view . '.twig', $data);

        //清理
        $this->view->forgetDriver('twig');

        return $content;
    }

    /**
     * 调用模板引擎渲染模板
     * @param $view
     * @param $param
     * @return string
     */
    protected function fetchView($view, $param)
    {
        // 处理变量中包含有对元数据嵌入的变量
        foreach ( $param as $k => $v ) {
            if ( str_contains($k, 'cid:') ) {
                $this->embedImage($k, $v, $param);
            }
        }
        return $this->view->fetch($view, $param);
    }

    /**
     * 对嵌入元数据的变量进行处理
     *
     * @param string $k
     * @param array|string $v
     * @param array $param
     */
    protected function embedImage(string &$k, array|string &$v, array &$param)
    {
        if ( is_array($v) && $v ) {
            if ( !isset($v[1]) ) {
                $v[1] = 'image';
            }
            [$img, $name] = $v;
            $embed = $this->embedData($img, $name);
        } else {
            $embed = $this->embed($v);
        }
        unset($param[$k]);
        $k         = substr($k, strlen('cid:'));
        $param[$k] = $embed;
    }

    /**
     * 构造发信人
     * @param Mailable $mailable
     * @return $this
     */
    protected function buildFrom(Mailable $mailable)
    {
        if ( !empty($mailable->from) ) {
            $this->from($mailable->from[0]['address'], $mailable->from[0]['name']);
        }
        return $this;
    }

    /**
     * 构造收信人
     * @param Mailable $mailable
     * @return $this
     */
    protected function buildRecipients(Mailable $mailable)
    {
        foreach ( ['to', 'cc', 'bcc', 'replyTo'] as $type ) {
            foreach ( $mailable->{$type} as $recipient ) {
                $this->{$type}($recipient['address'], $recipient['name']);
            }
        }

        return $this;
    }

    /**
     * 构造标题
     * @param Mailable $mailable
     * @return $this
     */
    protected function buildSubject(Mailable $mailable)
    {
        if ( $mailable->subject ) {
            $this->subject($mailable->subject);
        } else {
            $this->subject(Str::title(Str::snake(class_basename($mailable), ' ')));
        }

        return $this;
    }

    /**
     * 构造附件
     * @param Mailable $mailable
     * @return $this
     */
    protected function buildAttachments(Mailable $mailable)
    {
        foreach ( $mailable->attachments as $attachment ) {
            $this->attach($attachment['file'], $attachment['options']);
        }

        foreach ( $mailable->rawAttachments as $attachment ) {
            $this->attachData(
                $attachment['data'], $attachment['name'], $attachment['options']
            );
        }

        return $this;
    }

    /**
     * 执行回调
     *
     * @param Mailable $mailable
     * @return $this
     */
    protected function runCallbacks(Mailable $mailable)
    {
        foreach ( $mailable->callbacks as $callback ) {
            $callback($this->getSymfonyMessage());
        }

        return $this;
    }

    /**
     * Add a "from" address to the message.
     *
     * @param array|string $address
     * @param string|null $name
     * @return $this
     */
    public function from($address, $name = null)
    {
        is_array($address)
            ? $this->message->from(...$address)
            : $this->message->from(new Address($address, (string)$name));
        return $this;
    }

    /**
     * Set the "sender" of the message.
     *
     * @param array|string $address
     * @param string|null $name
     * @return $this
     */
    public function sender($address, $name = null)
    {
        is_array($address)
            ? $this->message->sender(...$address)
            : $this->message->sender(new Address($address, (string)$name));


        return $this;
    }

    /**
     * Set the "return path" of the message.
     *
     * @param string $address
     * @return $this
     */
    public function returnPath($address)
    {
        $this->message->returnPath($address);

        return $this;
    }

    /**
     * Add a recipient to the message.
     *
     * @param array|string $address
     * @param string|null $name
     * @param bool $override
     * @return $this
     */
    public function to($address, $name = null, $override = false)
    {
        if ( $override ) {
            is_array($address)
                ? $this->message->to(...$address)
                : $this->message->to(new Address($address, (string)$name));

            return $this;
        }

        return $this->addAddresses($address, $name, 'To');
    }


    /**
     * Add a carbon copy to the message.
     *
     * @param array|string $address
     * @param string|null $name
     * @param bool $override
     * @return $this
     */
    public function cc($address, $name = null, $override = false)
    {
        if ( $override ) {
            is_array($address)
                ? $this->message->cc(...$address)
                : $this->message->cc(new Address($address, (string)$name));

            return $this;
        }
        return $this->addAddresses($address, $name, 'Cc');
    }

    /**
     * Add a blind carbon copy to the message.
     *
     * @param string|array $address
     * @param string|null $name
     * @return $this
     */
    public function bcc($address, $name = null, $override = false)
    {
        if ( $override ) {
            is_array($address)
                ? $this->message->bcc(...$address)
                : $this->message->bcc(new Address($address, (string)$name));

            return $this;
        }
        return $this->addAddresses($address, $name, 'Bcc');
    }

    /**
     * Add a reply to address to the message.
     *
     * @param string|array $address
     * @param string|null $name
     * @return $this
     */
    public function replyTo($address, $name = null)
    {
        return $this->addAddresses($address, $name, 'ReplyTo');
    }


    /**
     * Add a recipient to the message.
     *
     * @param string|array $address
     * @param string $name
     * @param string $type
     * @return $this
     */
    protected function addAddresses($address, $name, $type)
    {
        if ( is_array($address) ) {
            $type = lcfirst($type);

            $addresses = collect($address)->map(function ($address, $key) {
                if ( is_string($key) && is_string($address) ) {
                    return new Address($key, $address);
                }

                if ( is_array($address) ) {
                    return new Address($address['email'] ?? $address['address'], $address['name'] ?? null);
                }

                if ( is_null($address) ) {
                    return new Address($key);
                }

                return $address;
            })->all();

            $this->message->{"{$type}"}(...$addresses);
        } else {
            $this->message->{"add{$type}"}(new Address($address, (string)$name));
        }

        return $this;
    }

    /**
     * Set the subject of the message.
     *
     * @param string $subject
     * @return $this
     */
    public function subject($subject)
    {
        $this->message->subject($subject);

        return $this;
    }

    /**
     * Set the message priority level.
     *
     * @param int $level
     * @return $this
     */
    public function priority($level)
    {
        $this->message->priority($level);

        return $this;
    }

    /**
     * Attach a file to the message.
     *
     * @param string $file
     * @param array $options
     * @return $this
     */
    public function attach($file, array $options = [])
    {
        if ( empty($options['name']) ) {
            $options['name'] = $file;
        }
        if ( empty($options['mime']) ) {
            $options['mime'] = mime_content_type($file);
        }
        $this->message->attachFromPath($file, $options['name'] ?? null, $options['mime'] ?? null);

        return $this;
    }


    /**
     * Attach in-memory data as an attachment.
     *
     * @param string $data
     * @param string $name
     * @param array $options
     * @return $this
     */
    public function attachData($data, $name, array $options = [])
    {
        $this->message->attach($data, $name, $options['mime'] ?? null);

        return $this;
    }


    /**
     * Embed a file in the message and get the CID.
     *
     * @param string $file
     * @return string
     */
    public function embed($file)
    {
        $mime = mime_content_type($file);
        $cid  = Str::random(10);
        $this->message->embedFromPath($file, $cid, $mime);
        return 'cid:' . $cid;
    }

    /**
     * Embed in-memory data in the message and get the CID.
     *
     * @param string $data
     * @param string $name
     * @param string|null $contentType
     * @return string
     */
    public function embedData($data, $name, $contentType = null)
    {
        $this->message->embed($data, $name, $contentType);
        return 'cid:' . $name;
    }


    /**
     * Get the underlying symfony Message instance.
     *
     * @return Email
     */
    public function getSymfonyMessage()
    {
        return $this->message;
    }

    /**
     * Dynamically pass missing methods to the Swift instance.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $callable = [$this->message, $method];

        return call_user_func_array($callable, $parameters);
    }
}

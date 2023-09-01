<?php

namespace yzh52521;

use Aws\Ses\SesClient;
use Aws\SesV2\SesV2Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use think\facade\Log;
use think\helper\Arr;
use think\helper\Str;
use think\Manager;
use yzh52521\mail\Mailer;
use yzh52521\mail\transport\ArrayTransport;
use yzh52521\mail\transport\LogTransport;
use yzh52521\mail\transport\SesTransport;
use yzh52521\mail\transport\SesV2Transport;

/**
 * Class Mail
 *
 * @package yzh52521
 * @mixin Mailer
 */
class MailManager extends Manager
{
    protected $app;
    /**
     * The array of resolved mailers.
     *
     * @var array
     */
    protected $mailers = [];

    /**
     * The registered custom driver creators.
     *
     * @var array
     */
    protected $customCreators = [];


    /**
     * @param $config
     * @return \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport
     */
    protected function createSmtpDriver($config)
    {
        $factory = new EsmtpTransportFactory;

        $transport = $factory->create(new Dsn(
            !empty($config['encryption']) && $config['encryption'] === 'tls' ? (($config['port'] == 465) ? 'smtps' : 'smtp') : '',
            $config['host'],
            $config['username'] ?? null,
            $config['password'] ?? null,
            $config['port'] ?? null,
            $config
        ));

        return $this->configureSmtpTransport($transport, $config);

    }

    /**
     * Configure the additional SMTP driver options.
     * @param \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport $transport
     * @param array $config
     * @return EsmtpTransport
     */
    protected function configureSmtpTransport(EsmtpTransport $transport, array $config)
    {
        $stream = $transport->getStream();

        if ($stream instanceof SocketStream) {
            if (isset($config['source_ip'])) {
                $stream->setSourceIp($config['source_ip']);
            }

            if (isset($config['timeout'])) {
                $stream->setTimeout($config['timeout']);
            }
        }

        return $transport;
    }

    protected function createSendmailDriver($config)
    {
        return new SendmailTransport($config['path'] ?? $this->getMailConfig('sendmail', 'path'));
    }

    /**
     * Create an instance of the Log Transport driver.
     *
     * @param array $config
     */
    protected function createLogDriver(array $config)
    {
        $logger = $this->app->make(LoggerInterface::class);

        if ($logger instanceof Log) {
            $logger = $logger->channel(
                $config['channel'] ?? $this->getMailConfig('mail', 'channel')
            );
        }

        return new LogTransport($logger);
    }

    /**
     * Create an instance of the Symfony Amazon SES Transport driver.
     *
     * @param array $config
     * @return SesTransport
     */
    protected function createSesDriver(array $config)
    {
        $config = array_merge(
            ['version' => 'latest'],
            $config
        );

        $config = Arr::except($config, ['transport']);

        return new SesTransport(
            new SesClient($this->addSesCredentials($config)),
            $config['options'] ?? []
        );
    }

    /**
     * Create an instance of the Symfony Amazon SES V2 Transport driver.
     *
     * @param array $config
     * @return SesV2Transport
     */
    protected function createSesV2Driver(array $config)
    {
        $config = array_merge(
            ['version' => 'latest'],
            $config
        );

        $config = Arr::except($config, ['transport']);

        return new SesV2Transport(
            new SesV2Client($this->addSesCredentials($config)),
            $config['options'] ?? []
        );
    }

    /**
     * Create an instance of the Symfony Mailgun Transport driver.
     *
     * @param array $config
     * @return \Symfony\Component\Mailer\Transport\TransportInterface
     */
    protected function createMailgunDriver(array $config)
    {
        $factory = new MailgunTransportFactory(null, $this->getHttpClient($config));

        if (!isset($config['secret'])) {
            $config = $this->app->config->get('mail.mailgun', []);
        }

        return $factory->create(new Dsn(
            'mailgun+' . ($config['scheme'] ?? 'https'),
            $config['endpoint'] ?? 'default',
            $config['secret'],
            $config['domain']
        ));
    }

    /**
     * Create an instance of the Symfony Postmark Transport driver.
     *
     * @param array $config
     * @return \Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkApiTransport
     */
    protected function createPostmarkDriver(array $config)
    {
        $factory = new PostmarkTransportFactory(null, $this->getHttpClient($config));

        $options = isset($config['message_stream_id'])
            ? ['message_stream' => $config['message_stream_id']]
            : [];

        return $factory->create(new Dsn(
            'postmark+api',
            'default',
            $config['token'] ?? $this->app->config->get('mail.postmark.token'),
            null,
            null,
            $options
        ));
    }


    /**
     * Create an instance of the Array Transport Driver.
     *
     */
    protected function createArrayDriver()
    {
        return new ArrayTransport;
    }


    /**
     * Add the SES credentials to the configuration array.
     *
     * @param array $config
     * @return array
     */
    protected function addSesCredentials(array $config)
    {
        if (!empty($config['key']) && !empty($config['secret'])) {
            $config['credentials'] = Arr::only($config, ['key', 'secret', 'token']);
        }

        return Arr::except($config, ['token']);
    }

    /**
     * Get a configured Symfony HTTP client instance.
     *
     * @return \Symfony\Contracts\HttpClient\HttpClientInterface|null
     */
    protected function getHttpClient(array $config)
    {
        if ($options = ($config['client'] ?? false)) {
            $maxHostConnections = Arr::pull($options, 'max_host_connections', 6);
            $maxPendingPushes   = Arr::pull($options, 'max_pending_pushes', 50);

            return HttpClient::create($options, $maxHostConnections, $maxPendingPushes);
        }
    }

    /**
     * @param null|string $name
     * @return Mailer
     */
    public function mailer(string $name = null): Mailer
    {
        $name = $name ?: $this->getDefaultDriver();

        return $this->mailers[$name] = $this->get($name);
    }

    /**
     * Attempt to get the mailer from the local cache.
     *
     * @param string $name
     * @return Mailer
     */
    protected function get($name)
    {
        return $this->mailers[$name] ?? $this->createDriver($name);
    }


    /**
     * 获取mail配置
     * @param string $mailer
     * @param null $name
     * @param null $default
     * @return array
     */
    public function getMailConfig($mailer, $name = null, $default = null)
    {
        if ($config = $this->getConfig("{$mailer}")) {
            return Arr::get($config, $name, $default);
        }

        throw new \InvalidArgumentException("mail [$mailer] not found.");
    }

    public function getConfig(string $name = null, $default = null)
    {
        if (!is_null($name)) {
            return $this->app->config->get('mail.' . $name, $default);
        }

        return $this->app->config->get('mail');
    }

    /**
     * @param $transport
     * @return \Symfony\Component\Mailer\Transport\TransportInterface
     */
    protected function createSymfonyTransport($transport)
    {

        $config = $this->getMailConfig($transport);

        if (isset($this->customCreators[$transport])) {
            return call_user_func($this->customCreators[$transport], $config);
        }

        $method = 'create' . Str::studly($transport) . 'Driver';

        if (method_exists($this, $method)) {
            return $this->$method($config);
        }

        throw new \InvalidArgumentException( "Unsupported mail transport [$transport]" );
    }

    protected function createDriver(string $transport)
    {
        $mailer = new Mailer(
            $this->app->view,
            $this->createSymfonyTransport($transport),
            $this->app
        );

        if ($this->app->bound('queue')) {
            $mailer->setQueue($this->app->queue);
        }

        foreach (['from', 'reply_to', 'to', 'return_path'] as $type) {
            $this->setGlobalAddress($mailer, $this->app->config->get('mail'), $type);
        }

        return $mailer;
    }


    protected function setGlobalAddress($mailer, array $config, string $type)
    {
        $address = Arr::get($config, $type, $this->app->config->get('mail.' . $type));

        if (is_array($address) && isset($address['address'])) {
            $mailer->{'always' . Str::studly($type)}($address['address'], $address['name']);
        }
    }

    /**
     * 默认驱动
     * @return string|null
     */
    public function getDefaultDriver()
    {
        return $this->getConfig('default');
    }

    /**
     * Disconnect the given mailer and remove from local cache.
     *
     * @param  string|null  $name
     * @return void
     */
    public function purge($name = null)
    {
        $name = $name ?: $this->getDefaultDriver();

        unset($this->mailers[$name]);
    }

    /**
     * Register a custom transport creator Closure.
     *
     * @param  string  $driver
     * @param  \Closure  $callback
     * @return $this
     */
    public function extend($driver, \Closure $callback)
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->mailer()->$method(...$parameters);
    }
}

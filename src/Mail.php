<?php

namespace yzh52521;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use think\facade\Log;
use think\helper\Arr;
use think\helper\Str;
use think\Manager;
use yzh52521\mail\Mailer;
use yzh52521\mail\transport\LogTransport;

/**
 * Class Mail
 *
 * @package yzh52521
 * @mixin Mailer
 */
class Mail extends Manager
{

    /**
     * @param $config
     * @return \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport
     */
    protected function createSmtpDriver($config)
    {
        $factory = new EsmtpTransportFactory;

        $transport = $factory->create(new Dsn(
            !empty($config['encryption']) && $config['encryption'] === 'tls' ? ( ( $config['port'] == 465 ) ? 'smtps' : 'smtp' ) : '',
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

        if ( $stream instanceof SocketStream ) {
            if ( isset($config['source_ip']) ) {
                $stream->setSourceIp($config['source_ip']);
            }

            if ( isset($config['timeout']) ) {
                $stream->setTimeout($config['timeout']);
            }
        }

        return $transport;
    }

    protected function createSendmailDriver($config)
    {
        return new SendmailTransport($config['path'] ?? $this->app->config->get('mail.sendmail.path'));
    }

    /**
     * Create an instance of the Log Transport driver.
     *
     * @param array $config
     */
    protected function createLogDriver(array $config)
    {
        $logger = $this->app->make(LoggerInterface::class);

        if ( $logger instanceof Log ) {
            $logger = $logger->channel(
                $config['channel'] ?? $this->app->config->get('mail.channel')
            );
        }

        return new LogTransport($logger);
    }


    /**
     * @param null|string $name
     * @return Mailer
     */
    public function mailer(string $name = null): Mailer
    {
        $name = $name ?: $this->getDefaultDriver();

        return $this->createDriver($name);
    }

    protected function resolveConfig(string $name)
    {
        return $this->app->config->get("mail.{$name}");
    }

    protected function createDriver(string $name)
    {
        $transport = parent::createDriver($name);

        $symfony = new SymfonyMailer($transport);

        /** @var Mailer $mailer */
        $mailer = $this->app->invokeClass(Mailer::class, [$symfony]);

        foreach ( ['from', 'reply_to', 'to', 'return_path'] as $type ) {
            $this->setGlobalAddress($mailer, $this->app->config->get('mail'), $type);
        }
        return $mailer;
    }

    protected function setGlobalAddress($mailer, array $config, string $type)
    {
        $address = Arr::get($config, $type, $this->app->config->get('mail.' . $type));

        if ( is_array($address) && isset($address['address']) ) {
            $mailer->{'always' . Str::studly($type)}($address['address'], $address['name']);
        }
    }

    /**
     * 默认驱动
     * @return string|null
     */
    public function getDefaultDriver()
    {
        return $this->app->config->get('mail.default');
    }
}

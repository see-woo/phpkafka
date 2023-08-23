<?php

declare(strict_types=1);

namespace longlang\phpkafka\Sasl;

use longlang\phpkafka\Config\CommonConfig;
use longlang\phpkafka\Socket\SocketInterface;

interface SaslInterface
{
    public function __construct(CommonConfig $config);

    /**
     * 获得授权名称.
     */
    public function getName(): string;

    /**
     * 返回授权信息.
     */
    public function getAuthBytes(): string;

    /**
     * 设置套接字
     *
     * @param SocketInterface $socket
     * @return void
     */
    public function setSocket(SocketInterface $socket): void;
}

<?php

declare(strict_types=1);

namespace longlang\phpkafka\Sasl;

use longlang\phpkafka\Config\CommonConfig;
use longlang\phpkafka\Exception\KafkaErrorException;
use Aws\Credentials\CredentialProvider;
use Aws\Signature\SignatureV4;
use GuzzleHttp\Psr7\Request;
use longlang\phpkafka\Socket\SocketInterface;

class AwsMskIamSasl implements SaslInterface
{
    const SIGN_ACTION = "kafka-cluster:Connect";
    const SIGN_SERVICE = "kafka-cluster";
    const SIGN_VERSION = "2020_10_22";
    const SIGN_ACTION_KEY = "action";
    const SIGN_HOST_KEY = "host";
    const SIGN_USER_AGENT_KEY = "user-agent";
    const SIGN_VERSION_KEY = "version";
    const QUERY_ACTION_KEY = "Action";

    /**
     * @var CommonConfig
     */
    protected $config;

    /**
     * @var SocketInterface
     */
    protected $socket;


    public function __construct(CommonConfig $config)
    {
        $this->config = $config;
    }

    public function setSocket(SocketInterface $socket): void
    {
        $this->socket = $socket;
    }

    /**
     * Authorization mode
     */
    public function getName(): string
    {
        return 'AWS_MSK_IAM';
    }

    /**
     * Generated the Signed JSON used by AWS_MSK_IAM as auth string
     * @throws KafkaErrorException
     */
    public function getAuthBytes(): string
    {
        $config = $this->config->getSasl();

        if (empty($this->socket) || empty($config['region'])) {
            throw new KafkaErrorException('AWS MSK config not found');
        }

        $host = $this->socket->getHost();
        $query = http_build_query([self::QUERY_ACTION_KEY => self::SIGN_ACTION]);
        $url = sprintf('kafka://%s/?', $host, $query);
        $request = new Request('GET', $url);

        $provider = CredentialProvider::defaultProvider();
        $credentials = $provider()->wait();
        
        $expiration = empty($config['expiration']) ? '+5 minutes' : $config['expiration'];

        $region = $config['region'];
        $signer = new SignatureV4(self::SIGN_SERVICE, $region);
        $signedRequest = $signer->presign($request, $credentials, $expiration);
        $signedUri = $signedRequest->getUri();

        parse_str($signedUri->getQuery(), $params);

        $headers = $signedRequest->getHeaders();

        $signedMap = array(
            self::SIGN_VERSION_KEY => self::SIGN_VERSION,
            self::SIGN_USER_AGENT_KEY => sprintf('php-kafka/sasl/aws_msk_iam/%s', PHP_VERSION),
            self::SIGN_ACTION_KEY => self::SIGN_ACTION,
            self::SIGN_HOST_KEY => $host
        );

        foreach ($params as $params_key => $params_value) {
            $signedMap[strtolower($params_key)] = $params_value;
        }

        foreach ($headers as $header_key => $header_value) {
            $header_key = strtolower($header_key);
            if ($header_key !== self::SIGN_HOST_KEY) {
                $signedMap[$header_key] = $header_value;
            }
        }

        return json_encode($signedMap, JSON_UNESCAPED_UNICODE);
    }
}
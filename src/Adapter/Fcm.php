<?php

namespace Kerox\Push\Adapter;

use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\Http\Client\Message;
use Cake\Utility\Hash;
use InvalidArgumentException;
use RuntimeException;

class Fcm extends AbstractAdapter {

    /**
     * @var string
     */
    public const PRIORITY_NORMAL = 'normal';

    /**
     * @var string
     */
    public const PRIORITY_HIGH = 'high';

    /**
     * Array for devices's token
     *
     * @var array
     */
    protected $tokens = [];

    /**
     * Array for the notification
     *
     * @var array
     */
    protected $notification = [];

    /**
     * Array of datas
     *
     * @var array
     */
    protected $datas = [];

    /**
     * Array of request parameters
     *
     * @var array
     */
    protected $parameters = [];

    /**
     * Array of payload
     *
     * @var array
     */
    protected $payload = [];

    /**
     * Default config
     *
     * @var array
     */
    protected $_defaultConfig = [
        'parameters' => [
            'collapse_key' => null,
            'priority' => self::PRIORITY_NORMAL,
            'content_available' => false,
            'mutable_content' => false,
            'time_to_live' => 0,
            'restricted_package_name' => null,
            'dry_run' => false,
        ],
        'http' => [],
    ];

    /**
     * List of keys allowed to be used in notification array.
     *
     * @var array
     */
    protected $_allowedNotificationKeys = [
        'title',
        'body',
        'icon',
        'sound',
        'badge',
        'tag',
        'color',
        'click_action',
        'body_loc_key',
        'body_loc_args',
        'title_loc_key',
        'title_loc_args',
    ];

    /**
     *
     * @throws \Exception
     */
    public function __construct() {
        if (Configure::check('Push.adapters.Fcm') === false) {
            try {
                Configure::load('push');
            } catch (\Exception $e) {
                throw new RuntimeException($e->getMessage());
            }
        }

        $config = Configure::read('Push.adapters.Fcm');

        parent::__construct($config);
    }

    /**
     * Getter for tokens
     *
     * @return array
     */
    public function getTokens() {
        return $this->tokens;
    }

    /**
     * Setter for tokens
     *
     * @param array $tokens Array of devices's token
     *
     * @return $this
     */
    public function setTokens(array $tokens) {
        $this->_checkTokens($tokens);
        $this->tokens = $tokens;

        return $this;
    }

    /**
     * Getter for notification
     *
     * @return array
     */
    public function getNotification() {
        return $this->notification;
    }

    /**
     * Setter for notification
     *
     * @param array $notification Array of keys for the notification
     *
     * @return $this
     */
    public function setNotification(array $notification) {
        $this->_checkNotification($notification);
        if (!isset($notification['icon'])) {
            $notification['icon'] = 'myicon';
        }
        $this->notification = $notification;

        return $this;
    }

    /**
     * Getter for datas
     *
     * @return array
     */
    public function getDatas() {
        return $this->datas;
    }

    /**
     * Setter for datas
     *
     * @param array $datas Array of datas for the push
     *
     * @return $this
     */
    public function setDatas(array $datas) {
        $this->_checkDatas($datas);
        foreach ($datas as $key => $value) {
            if (\is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            $datas[$key] = (string)$value;
        }
        $this->datas = $datas;

        return $this;
    }

    /**
     * Getter for parameters
     *
     * @return array
     */
    public function getParameters() {
        return $this->parameters;
    }

    /**
     * Setter for parameters
     *
     * @param array $parameters Array of parameters for the push
     *
     * @return $this
     */
    public function setParameters(array $parameters) {
        $this->_checkParameters($parameters);
        $this->parameters = Hash::merge($this->getConfig('parameters'), $parameters);

        return $this;
    }

    /**
     * Getter for payload
     *
     * @return array
     */
    public function getPayload() {
        $notification = $this->getNotification();
        if (!empty($notification)) {
            $this->payload['notification'] = $notification;
        }

        $datas = $this->getDatas();
        if (!empty($datas)) {
            $this->payload['data'] = $datas;
        }

        return $this->payload;
    }

    /**
     * Execute the push
     *
     * @return bool
     */
    public function send() {
        $message = $this->_buildMessage();
        $options = $this->_getHttpOptions();

        $http = new Client();
        $this->response = $http->post($this->getConfig('api.url'), $message, $options);

        return $this->response->getStatusCode() === Message::STATUS_OK;
    }

    /**
     * Check tokens's array
     *
     * @param array $tokens Token's array
     *
     * @throws \InvalidArgumentException
     * @return void
     */
    private function _checkTokens(array $tokens) {
        if (empty($tokens) || \count($tokens) > 1000) {
            throw new InvalidArgumentException('Array must contain at least 1 and at most 1000 tokens.');
        }
    }

    /**
     * Check notification's array
     *
     * @param array $notification Notification's array
     *
     * @throws \InvalidArgumentException
     * @return void
     */
    private function _checkNotification(array $notification) {
        if (empty($notification) || !isset($notification['title'])) {
            throw new InvalidArgumentException('Array must contain at least a key title.');
        }

        $notAllowedKeys = [];
        foreach ($notification as $key => $value) {
            if (!\in_array($key, $this->_allowedNotificationKeys, true)) {
                $notAllowedKeys[] = $key;
            }
        }

        if (!empty($notAllowedKeys)) {
            $notAllowedKeys = implode(', ', $notAllowedKeys);

            throw new InvalidArgumentException("The following keys are not allowed: {$notAllowedKeys}");
        }
    }

    /**
     * Check datas's array
     *
     * @param array $datas Datas's array
     *
     * @throws \InvalidArgumentException
     * @return void
     */
    private function _checkDatas(array $datas) {
        if (empty($datas)) {
            throw new InvalidArgumentException('Array can not be empty.');
        }
    }

    /**
     * Check parameters's array
     *
     * @param array $parameters Parameters's array
     *
     * @throws \InvalidArgumentException
     * @return void
     */
    private function _checkParameters(array $parameters) {
        if (empty($parameters)) {
            throw new InvalidArgumentException('Array can not be empty.');
        }
    }

    /**
     * Build the message
     *
     * @return string
     */
    private function _buildMessage() {
        $tokens = $this->getTokens();
        $message = (\count($tokens) > 1) ? ['registration_ids' => $tokens] : ['to' => current($tokens)];

        $payload = $this->getPayload();
        if (!empty($payload)) {
            $message += $payload;
        }

        $parameters = $this->getParameters();
        if (!empty($parameters)) {
            $message += $parameters;
        }

        return json_encode($message);
    }

    /**
     * Return options for the HTTP request
     *
     * @return array
     */
    private function _getHttpOptions() {
        $options = Hash::merge($this->getConfig('http'), [
            'type' => 'json',
            'headers' => [
                'Authorization' => 'key=' . $this->getConfig('api.key'),
                'Content-Type' => 'application/json',
            ],
        ]);

        return $options;
    }

}

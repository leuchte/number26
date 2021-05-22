<?php

/**
 * Number26
 *
 * @author   André Daßler <mail@leuchte.net>
 * @author   Steven Briscoe <me@stevenbriscoe.com>
 * @license  http://opensource.org/licenses/MIT
 * @package  Number26
 */

namespace leuchte\Number26;

use \DateTime;
use \Exception;
use Ramsey\Uuid\Uuid;

class Number26
{
    const STORE_COOKIES = 1;

    const STORE_FILE = 2;

    /**
     * API Base url
     */
    protected $apiUrl = 'https://api.tech26.de';

    /**
     * Token used as authentification
     */
    protected $accessToken = null;

    /**
     * Token used if access is expired
     */
    protected $refreshToken = null;

    /**
     * Unique token to identify device
     */
    protected $deviceToken = null;

    /**
     * Time when session expire
     */
    protected $expiresTime = 0;

    /**
     * JSON object for the api response
     */
    protected $apiResponse;

    /**
     * Returned header after an api call
     */
    protected $apiHeader;

    /**
     * Response informations as array after an api call
     */
    protected $apiInfo;

    /**
     * Show curl errors if thrown
     */
    protected $apiError;

    /**
     * Temporary storage for csv output
     */
    protected $csvOutput = '';

    /**
     * The type of store to use for the tokens
     */
    protected $store;

    /**
     * Path to store access tokens
     */
    protected $storeAccessTokensFile;

    /**
     * Create a new Number26 instance
     *
     * @param string $username
     * @param string $password
     */
    public function __construct($username, $password, $store = self::STORE_COOKIES)
    {
        $this->store = $store;

        if($this->store == self::STORE_FILE)
        {
            $this->storeAccessTokensFile = $_SERVER['HOME'] . "/.n26";
        }

        $this->checkDeviceToken();

        if (! $this->isValidConnection()) {
            $apiResult = $this->callApi('/oauth/token', [
                'grant_type' => 'password',
                'username' => $username,
                'password' => $password
            ], $basic = true, 'POST');

            if(isset($apiResult->error) && $apiResult->error == "mfa_required") {
                $this->requestMfaApproval($apiResult->mfaToken);

                $apiResult = $this->completeAuthenticationFlow($apiResult->mfaToken);

                if(is_null($apiResult)) {
                    throw new Exception("2FA request expired.");
                }
            }

            if (isset($apiResult->error)) {
                throw new Exception($apiResult->error . ': ' . $apiResult->detail);
            }
            $this->setProperties($apiResult);
        } else {
            $this->loadProperties();
        }
    }

    /**
     * Check for a device token and set it if necessary
     */
    protected function checkDeviceToken()
    {
        if ($this->deviceToken === null) {
            $this->deviceToken = Uuid::uuid4();
        }

        return $this;
    }

    /**
     * Request to send a 2FA confirmation to the user's mobile device
     */
    protected function requestMfaApproval($mfaToken)
    {
        return $this->callApi('/api/mfa/challenge', [
            'challengeType' => 'oob',
            'mfaToken' => $mfaToken
        ], $basic = true, 'POST', $json = true);
    }

    /**
     * Pool the N26 API until the user has confirmed 2FA request
     */
    protected function completeAuthenticationFlow($mfaToken, $wait = 5, $max = 60)
    {
        $futureTime = time() + $max;

        while($futureTime > time()) {
            $apiResult = $this->callApi('/oauth/token', [
                'grant_type' => 'mfa_oob',
                'mfaToken' => $mfaToken
            ], $basic = true, 'POST');

            if(isset($apiResult->access_token)) {
                return $apiResult;
            }

            sleep($wait);
        }

        return null;
    }

    /**
     * If the access token is not valid anymore, we refresh our session
     */
    protected function refreshSession()
    {
        $apiResult = $this->callApi('/oauth/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->refreshToken
        ], $basic = true, 'POST');

        if (isset($apiResult->error) || isset($apiResult->error_description)) {
            throw new Exception($apiResult->error . ': ' . $apiResult->error_description);
        }
        $this->setProperties($apiResult);
    }

    /**
     * Set tokens and cookies for our session
     */
    protected function setProperties($apiResult)
    {
        $this->storeTokens($apiResult->access_token, $apiResult->refresh_token, $apiResult->expires_in);
    }

    /**
     * Load the tokens from cookies or a file
     */
    protected function loadProperties()
    {
        switch ($this->store) {
            case self::STORE_FILE:
                $tokens = json_decode(file_get_contents($this->storeAccessTokensFile), true);
                if(is_null($tokens))
                {
                    throw new Exception("Failed to load config from: " . $this->storeAccessTokensFile);
                }
                else
                {
                    $this->accessToken = $tokens["n26Token"];
                    $this->refreshToken = $tokens["n26Refresh"];
                    $this->expiresTime = $tokens["n26Expire"];
                }
                break;
            case self::STORE_COOKIES:
                $this->accessToken = $_COOKIE["n26Token"];
                $this->refreshToken = $_COOKIE["n26Refresh"];
                $this->expiresTime = $_COOKIE["n26Expire"];
                break;
        }
    }

    /**
     * Store the tokens to cookies or a file
     */
    protected function storeTokens($accessToken, $refreshToken, $expiresIn)
    {
        $this->accessToken = $accessToken;
        $this->refreshToken = $refreshToken;
        $this->expiresTime = time() + $expiresIn;

        switch ($this->store) {
            case self::STORE_FILE:
                $tokens = [
                    'n26Expire' => $this->expiresTime,
                    'n26Token' => $this->accessToken,
                    'n26Refresh' => $this->refreshToken
                ];
                file_put_contents($this->storeAccessTokensFile, json_encode($tokens));
                break;
            case self::STORE_COOKIES:
                setcookie('n26Expire', $this->expiresTime, $this->expiresTime);
                setcookie('n26Token', $this->accessToken, $this->expiresTime);
                setcookie('n26Refresh', $this->refreshToken);
                break;
        }
    }

    /**
     * Is there a valid auth cookie?
     *
     * @return boolean true if valid
     */
    public function isLoggedIn()
    {
        return $this->isValidConnection();
    }

    /**
     * Is the saved token valid?
     *
     * @return boolean true if valid
     */
    protected function isValidConnection()
    {
        switch ($this->store) {
            case self::STORE_FILE:
                return file_exists($this->storeAccessTokensFile);
                break;
            case self::STORE_COOKIES:
                return isset($_COOKIE['n26Expire']) && isset($_COOKIE['n26Token']) && isset($_COOKIE['n26Refresh']);
                break;
        }
    }

    /**
     * Remove the cookie if the connection is expired
     */
    public function logout()
    {
        switch ($this->store) {
            case self::STORE_FILE:
                @unlink($this->storeAccessTokensFile);
                break;
            case self::STORE_COOKIES:
                setcookie('n26Expire', '', time() - 1000);
                setcookie('n26Token', '', time() - 1000);
                setcookie('n26Refresh', '', time() - 1000);
                break;
        }
    }

    /**
     * Build a valid API Url and send it via curl
     *
     * @param  string  $apiResource
     * @param  array|string  $params
     * @param  boolean $basic       Basic auth or with bearer token
     * @param  string  $method      Default GET
     * @return object               $this->apiResponse. Further information in $this->apiHeader and $this->apiInfo
     */
    protected function callApi($apiResource, $params = null, $basic = false, $method = 'GET', $json = false)
    {
        if ($basic == true && is_array($params) && count($params)) {
            $apiResource = $apiResource . '?' . http_build_query($params);
        }
        $this->callCurl($apiResource, $params, $basic, $method, $json);

        return $this->apiResponse;
    }

    /**
     * Set up curl and call the resource
     *
     * @param  string  $apiResource
     * @param  array|string  $params
     * @param  boolean $basic       Basic auth or with bearer token
     * @param  string  $method      Default GET
     * @return string Response
     */
    protected function callCurl($apiResource, $params, $basic, $method, $json = false)
    {
        $curl = curl_init($this->apiUrl . $apiResource);
        $curlOptions = [
            CURLOPT_HEADER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $this->getHeader($basic, $json),
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false
        ];

        if ($method == 'POST') {
            if($json) {
                $params = json_encode($params);
            }
            $curlOptions[CURLOPT_POST] = true;
            $curlOptions[CURLOPT_POSTFIELDS] = $params;
        }
        curl_setopt_array($curl, $curlOptions);

        $response = curl_exec($curl);
        $this->apiInfo = curl_getinfo($curl);
        $this->apiHeader = substr($response, 0, $this->apiInfo['header_size']);
        $this->apiResponse = substr($response, $this->apiInfo['header_size']);

        if (strpos($this->apiInfo['content_type'], 'json')) {
            $this->apiResponse = json_decode($this->apiResponse);
        }
        if (isset($this->apiResponse->error) && $this->apiResponse->error == 'invalid_token') {
            $this->refreshSession();
            $this->callCurl($apiResource, $params, $basic, $method, $json);
        }

        if (false === $response) {
            $errno = curl_errno($curl);
            $errmsg = curl_error($curl);
            $this->apiError = 'curl-Error: ' . $errno . ': ' . $errmsg;
        }
    }

    /**
     * Build the header for authorization
     *
     * @param  boolean $basic True if bearer token is used
     * @return array         Built header
     */
    protected function getHeader($basic = false, $json = false)
    {
        $header = ($basic) ? 'Basic bmF0aXZlaW9zOg==' : 'Bearer ' . $this->accessToken;
        $httpHeader = [];
        $httpHeader[] = 'Authorization: ' . $header;
        $httpHeader[] = 'Accept: */*';
        $httpHeader[] = 'device-token: ' . $this->deviceToken;

        if($json) {
            $httpHeader[] = 'Content-Type: application/json';
        }

        return $httpHeader;
    }

    /**
     * Convert an key-value array into an query string
     *
     * @param  array  $params
     * @return string
     */
    protected function buildParams(array $params = [])
    {
        $paramString = '';
        if (count($params)) {
            $paramString .= '?' . http_build_query($params);
        }

        return $paramString;
    }

    /**
     * Returns basic information about the account holder
     *
     * @param  boolean $full true for more informations
     * @return object
     */
    public function getMe($full = false)
    {
        return $this->callApi('/api/me' . (($full) ? '?full=true' : ''));
    }

    /**
     * All created spaces
     *
     * @return object
     */
    public function getSpaces()
    {
        return $this->callApi('/api/spaces');
    }

    /**
     * Information about space with id $id
     *
     * @param  string $id
     * @return object
     */
    public function getSpace($id)
    {
        return $this->callApi('/api/spaces/' . $id);
    }

    /**
     * Shows all registered cards
     *
     * @return object
     */
    public function getCards()
    {
        return $this->callApi('/api/v2/cards');
    }

    /**
     * Basic account information
     *
     * @return object
     */
    public function getAccounts()
    {
        return $this->callApi('/api/accounts');
    }

    /**
     * All saved addresses, ie. for shipping
     *
     * @return object
     */
    public function getAddresses()
    {
        return $this->callApi('/api/addresses');
    }

    /**
     * Address with id $id
     *
     * @param  string $id
     * @return object
     */
    public function getAddress($id)
    {
        return $this->callApi('/api/addresses/' . $id);
    }

    /**
     * Get all transactions
     *
     * @param  array $params limit, textFilter
     * @return object
     */
    public function getTransactions($params = [])
    {
        $params = (isset($params)) ? $this->buildParams($params) : '';
        return $this->callApi('/api/smrt/transactions' . $params);
    }

    /**
     * Get a single transaction with the id $id
     *
     * @param  string $id
     * @return object
     */
    public function getTransaction($id)
    {
        return $this->callApi('/api/smrt/transactions/' . $id);
    }

    /**
     * All transfer contacts
     *
     * @return object
     */
    public function getContacts()
    {
        return $this->callApi('/api/smrt/contacts');
    }

    /**
     * Possible categories to put a transaction in
     *
     * @return object
     */
    public function getCategories()
    {
        return $this->callApi('/api/smrt/categories');
    }

    /**
     * Get a csv report
     *
     * @param  DateTime $startDate
     * @param  DateTime $endDate
     * @param  string   $saveFileLocation Where to save the report
     * @return object
     */
    public function getReport(DateTime $startDate, DateTime $endDate, $saveFileLocation = '')
    {
        // Use the whole start- and endday in microseconds
        $start = $startDate->setTime(0, 0)->getTimestamp() * 1000;
        $end = $endDate->setTime(23, 59, 59)->getTimestamp() * 1000;
        $response = $this->callApi('/api/smrt/reports/' . $start . '/' . $end . '/statements');
        if ($saveFileLocation != '') {
            $fileName = $startDate->format('Ymd') . '-' . $endDate->format('Ymd') . '.csv';
            file_put_contents($saveFileLocation . '/' . $fileName, $response);
        }

        return $response;
    }

    /**
     * Build a csv file for a import. Here in "money money format (german)"
     *
     * @param  integer $offset
     * @param  integer $limit
     */
    public function getCsv($offset = 0, $limit = 50)
    {
        $transactions = $this->getTransactions(['sort' => 'visibleTS', 'dir' => 'ASC', 'offset' => $offset, 'limit' => $limit]);
        $sep = ';';
        $csvOutput = '';
        foreach ($transactions as $transaction) {
            $csvOutput .= date('d.m.Y', ($transaction->visibleTS / 1000)) . $sep . $sep;
            $csvOutput .= (isset($transaction->confirmed) ? date('d.m.Y', ($transaction->confirmed / 1000)) : date('d.m.Y', ($transaction->visibleTS / 1000))) . $sep;
            $csvOutput .= trim(isset($transaction->partnerName) ? $transaction->partnerName : $transaction->merchantName) . $sep;
            $csvOutput .= trim(isset($transaction->referenceText) ? $transaction->referenceText : $transaction->merchantName) . $sep;
            $csvOutput .= (isset($transaction->partnerIban) ? $transaction->partnerIban : '') . $sep;
            $csvOutput .= (isset($transaction->partnerBic) ? $transaction->partnerBic : '') . $sep;
            $csvOutput .= number_format($transaction->amount, 2, ',', '.') . $sep;
            $csvOutput .= $transaction->currencyCode . "\r\n";
        }

        $this->csvOutput = $this->csvOutput . $csvOutput;

        if (isset($transactions->paging->next)) {
            $this->getCsv($offset + $limit);

            return;
        }

        $this->writeCsvFile();
    }

    /**
     * Write the csv file
     */
    protected function writeCsvFile()
    {
        $csvOutput = 'Datum;Wertstellung;Kategorie;Name;Verwendungszweck;Konto;Bank;Betrag;Währung';
        file_put_contents('number26_account_data.csv', $csvOutput . "\r\n" . $this->csvOutput);
    }
}

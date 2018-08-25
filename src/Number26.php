<?php

/**
 * Number26
 *
 * @author   André Daßler <mail@leuchte.net>
 * @license  http://opensource.org/licenses/MIT
 * @package  Number26
 */

namespace leuchte\Number26;

use \DateTime;

class Number26
{
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
     * Create a new Number26 instance
     *
     * @param string $username
     * @param string $password
     */
    public function __construct($username, $password)
    {
        if (!$this->isValidConnection()) {
            $apiResult = $this->callApi('/oauth/token', [
                'grant_type' => 'password',
                'username' => $username,
                'password' => $password], $basic = true, 'POST');

            if (isset($apiResult->error) || isset($apiResult->error_description)) {
                die($apiResult->error . ': ' . $apiResult->error_description);
            }
            $this->setPropertiesAndCookies($apiResult);
        }
    }

    /**
     * If the access token is not valid anymore, we refresh our session
     *
     */
    protected function refreshSession()
    {
        $apiResult = $this->callApi('/oauth/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->refreshToken
        ], $basic = true, 'POST');

        if (isset($apiResult->error) || isset($apiResult->error_description)) {
            die($apiResult->error . ': ' . $apiResult->error_description);
        }
        $this->setPropertiesAndCookies($apiResult);
    }

    /**
     * Set tokens and cookies for our session
     */
    protected function setPropertiesAndCookies($apiResult)
    {
        $this->accessToken = $apiResult->access_token;
        $this->refreshToken = $apiResult->refresh_token;
        $this->expiresTime = time() + $apiResult->expires_in;

        setcookie('n26Expire', $this->expiresTime, $this->expiresTime);
        setcookie('n26Token', $this->accessToken, $this->expiresTime);
        setcookie('n26Refresh', $this->refreshToken);
    }

    /**
     * Is there a valid auth cookie?
     *
     * @return boolean true if valid
     */
    public function isLoggedIn()
    {
        if ($this->isValidConnection) {
            return true;
        }

        return false;
    }

    /**
     * Is the saved token valid?
     *
     * @return boolean true if valid
     */
    protected function isValidConnection()
    {
        if (isset($_COOKIE['n26Expire']) && isset($_COOKIE['n26Token']) && isset($_COOKIE['n26Refresh'])) {
            $this->expiresTime = $_COOKIE['n26Expire'];
            if (time() < $this->expiresTime) {
                $this->accessToken = $_COOKIE['n26Token'];
                $this->refreshToken = $_COOKIE['n26Refresh'];

                return true;
            }
        }
        $this->logout();

        return false;
    }

    /**
     * Remove the cookie if the connection is expired
     */
    public function logout()
    {
        setcookie('n26Expire', '', time() - 1000);
        setcookie('n26Token', '', time() - 1000);
        setcookie('n26Refresh', '', time() - 1000);
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
    protected function callApi($apiResource, $params = null, $basic = false, $method = 'GET')
    {
        if ($basic == true && is_array($params) && count($params)) {
            $apiResource = $apiResource . '?' . http_build_query($params);
        }
        $this->callCurl($apiResource, $params, $basic, $method);

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
    protected function callCurl($apiResource, $params, $basic, $method)
    {
        $curl = curl_init($this->apiUrl . $apiResource);
        $curlOptions = [
            CURLOPT_HEADER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $this->getHeader($basic),
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false];

        if ($method == 'POST') {
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
            $this->callCurl($apiResource, $params, $basic, $method);
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
    protected function getHeader($basic = false)
    {
        $header = ($basic) ? 'Basic bXktdHJ1c3RlZC13ZHBDbGllbnQ6c2VjcmV0' : 'Bearer ' . $this->accessToken;
        $httpHeader = [];
        $httpHeader[] = 'Authorization: ' . $header;
        $httpHeader[] = 'Accept: */*';

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
     */
    public function getSpaces()
    {
        return $this->callApi('/api/spaces');
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
     * Information about card with id $id
     *
     * @param  string $id
     * @return object
     */
    public function getCard($id)
    {
        return $this->callApi('/api/cards/' . $id);
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
     *  deprecated; don't use it anymore
     *
     * @param  array $params sort, offset, limit, dir, textFilter
     * @return object
     */
    public function getTransactions($params)
    {
        return $this->getSmrtTransactions($params);
    }

    /**
     * Get all smart transactions
     *
     * @param  array $params limit, textFilter
     * @return object
     */
    public function getSmrtTransactions($params)
    {
        $params = (isset($params)) ? $this->buildParams($params) : '';
        return $this->callApi('/api/smrt/transactions' . $params);
    }

    /**
     * Get a single transaction with the id $id
     * deprecated; don't use it anymore
     *
     * @param  string $id
     * @return object
     */
    public function getTransaction($id)
    {
        return $this->callApi('/api/transactions/' . $id);
    }

    /**
     * Get a smart single transaction with the id $id
     *
     * @param  string $id
     * @return object
     */
    public function getSmrtTransaction($id)
    {
        return $this->callApi('/api/smrt/transactions/' . $id);
    }

    /**
     * Get all transfer recipients so far
     *
     * @return object
     */
    public function getRecipients()
    {
        return $this->callApi('/api/transactions/recipients');
    }

    /**
     * The more detailed version of getAddresses()
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

    /**
     * Create a new transaction. You have to approve the transfer on the linked device
     *
     * @param  integer $amount
     * @param  string $pin       The personal pin for transactions
     * @param  [type] $bic       Receipients BIC
     * @param  [type] $iban      Receipients IBAN
     * @param  [type] $name      Receivers name
     * @param  [type] $reference
     *
     * @return object
     */
    public function makeTransfer($amount, $pin, $bic, $iban, $name, $reference)
    {
        $content = [
            'pin' => $pin,
            'transaction' => [
                'partnerBic' => $bic,
                'amount' => $amount,
                'type' => 'DT',
                'partnerIban' => $iban,
                'partnerName' => $name,
                'referenceText' => $reference
        ]];

        return $this->callApi('/api/transactions', json_encode($content), false, 'POST');
    }
}

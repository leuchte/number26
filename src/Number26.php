<?php

/**
 * Number26
 * 
 * @author   André Daßler <mail@leuchte.net>
 * @license  http://opensource.org/licenses/MIT
 * @package  Number26
 */

namespace leuchte\Number26;

class Number26
{
    /**
     * API Base url
     */
    protected $apiUrl = 'https://api.tech26.de';

    /**
     * Token uses as authentification
     */
    protected $accessToken = null;

    /**
     * Seconds until session expire
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
     * Informations after an api call
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
                'grant_type'    => 'password',
                'username'      => $username,
                'password'      => $password], true, 'POST');
            $this->accessToken = $apiResult->access_token;
            $this->expiresTime = time() + $apiResult->expires_in;
            setcookie('n26Expire', $this->expiresTime, $this->expiresTime);
            setcookie('n26Token', $this->accessToken, $this->expiresTime);
        }
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
        if (isset($_COOKIE['n26Expire']) && isset($_COOKIE['n26Token'])) {
            $this->expiresTime = $_COOKIE['n26Expire'];
            if (time() < $this->expiresTime) {
                $this->accessToken = $_COOKIE['n26Token'];
                
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

        if (false === $response) {
            $errno = curl_errno($curl);
            $errmsg = curl_error($curl);
            $this->apiError = 'curl-Error: ' . $errno . ': ' . $errmsg;
        }
        $this->apiResponse = json_decode(substr($response, $this->apiInfo['header_size']));

        return $this->apiResponse;
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
        $httpHeader[] = 'Accept: application/json';
        $httpHeader[] = 'Content-Type: application/json';

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
     * Shows all registered cards
     * 
     * @return object
     */
    public function getCards()
    {
        return $this->callApi('/api/cards/offer');
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
     * 
     * @param  array $params sort, offset, limit, dir, textFilter
     * @return object
     */
    public function getTransactions(...$params)
    {
        $params = (isset($params[0])) ? $this->buildParams($params[0]) : '';
        return $this->callApi('/api/transactions' . $params);
    }

    /**
     * Get all smart(?) transactions
     * 
     * @param  array $params limit, textFilter
     * @return object
     */
    public function getSmrtTransactions(...$params)
    {
        $params = (isset($params[0])) ? $this->buildParams($params[0]) : '';
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
        return $this->callApi('/api/transactions/' . $id);
    }

    /**
     * Get a smart(?) single transaction with the id $id
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
        foreach ($transactions->data as $transaction) {
            $csvOutput .= (isset($transaction->confirmed) ? date('d.m.Y', ($transaction->confirmed / 1000)) : date('d.m.Y', ($transaction->visibleTS / 1000))) . $sep;
            $csvOutput .= date('d.m.Y', ($transaction->visibleTS / 1000)) . $sep . $sep;
            $csvOutput .= trim(isset($transaction->partnerName) ? $transaction->partnerName : $transaction->merchantName) . $sep;
            $csvOutput .= trim(isset($transaction->referenceText) ? $transaction->referenceText : $transaction->merchantName) . $sep;
            $csvOutput .= (isset($transaction->partnerIban) ? $transaction->partnerIban : '') . $sep;
            $csvOutput .= (isset($transaction->partnerBic) ? $transaction->partnerBic : '') . $sep;
            $csvOutput .= (in_array($transaction->type, ['FT', 'PT', 'AA'])) ? '-' : '';
            $csvOutput .= number_format($transaction->amount, 2, ',', '.') . $sep;
            $csvOutput .= $transaction->currencyCode->currencyCode . "\r\n";
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